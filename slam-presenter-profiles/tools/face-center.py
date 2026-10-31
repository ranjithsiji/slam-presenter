#!/usr/bin/env python3
"""Find the face in each presenter photo and work out a face point + zoom for the square crop.

    pip install opencv-python-headless numpy pillow
    wp eval-file tools/photo-list.php > photos.json
    python3 tools/face-center.py photos.json focus.json --sheet preview.png
    wp eval-file tools/apply-focus.php focus.json

The square shows the face at about FACE_SHARE of its width, a little above the middle
(head and shoulders). Photos where no face is found are listed and left for manual adjustment
(Science Slam → Photos → Adjust).
"""
import argparse
import io
import json
import os
import sys
import urllib.request

try:
    import cv2
    import numpy as np
except ImportError:
    sys.exit('Needs OpenCV: pip install opencv-python-headless numpy pillow')

MODEL_URL = 'https://github.com/opencv/opencv_zoo/raw/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx'
FACE_SHARE = 0.30   # face width / square width
HEADROOM = 0.08     # face centre sits this far (of the square) above the middle
MAX_ZOOM = 3.0


def model_path():
    path = os.path.join(os.path.expanduser('~/.cache/ssp'), 'face_detection_yunet_2023mar.onnx')
    if not os.path.exists(path):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        print('Downloading face model…', file=sys.stderr)
        urllib.request.urlretrieve(MODEL_URL, path)
    return path


def load(url):
    req = urllib.request.Request(url, headers={'User-Agent': 'ssp-face-center'})
    data = urllib.request.urlopen(req, timeout=60).read()
    img = cv2.imdecode(np.frombuffer(data, np.uint8), cv2.IMREAD_COLOR)
    if img is None:
        # OpenCV wheels usually lack AVIF/HEIC (WordPress may serve AVIF); Pillow reads them.
        try:
            from PIL import Image, ImageOps
        except ImportError:
            raise RuntimeError('format not readable by OpenCV; pip install pillow')
        pil = ImageOps.exif_transpose(Image.open(io.BytesIO(data))).convert('RGB')
        img = cv2.cvtColor(np.asarray(pil), cv2.COLOR_RGB2BGR)
    return img


def best_face(detector, img):
    h, w = img.shape[:2]
    detector.setInputSize((w, h))
    _, faces = detector.detect(img)
    if faces is None or not len(faces):
        # Small faces in big full-length photos: try again on a 2x upscale.
        if max(w, h) < 2000:
            detector.setInputSize((w * 2, h * 2))
            _, faces = detector.detect(cv2.resize(img, (w * 2, h * 2), interpolation=cv2.INTER_CUBIC))
            if faces is not None and len(faces):
                faces = faces.copy()
                faces[:, :14] /= 2
        if faces is None or not len(faces):
            return None
    # Largest confident face: the presenter, not someone in the background.
    return max(faces, key=lambda f: f[2] * f[3] * f[14])[:4]


def framing(w, h, face):
    x, y, fw, fh = (float(v) for v in face)
    short = min(w, h)
    zoom = min(MAX_ZOOM, max(1.0, short * FACE_SHARE / fw))
    side = short / zoom
    cx = x + fw / 2
    cy = y + fh / 2 + HEADROOM * side
    return '%.1f%% %.1f%%' % (100 * cx / w, 100 * cy / h), round(float(zoom), 2)


def window(w, h, focus, zoom):
    """Same calculation as SSP_Presenter::frame(), in pixels: (left, top, side)."""
    fx, fy = (float(v.rstrip('%')) / 100 for v in focus.split())
    side = min(w, h) / zoom
    left = min(w - side, max(0, fx * w - side / 2))
    top = min(h - side, max(0, fy * h - side / 2))
    return int(left), int(top), int(side)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('photos', help='JSON from tools/photo-list.php')
    ap.add_argument('out', help='focus JSON for tools/apply-focus.php')
    ap.add_argument('--sheet', help='write a contact sheet of the resulting squares (PNG)')
    ap.add_argument('--skip-adjusted', action='store_true', help='leave out photos that already have a face point')
    args = ap.parse_args()

    detector = cv2.FaceDetectorYN.create(model_path(), '', (320, 320), 0.7, 0.3, 5000)
    with open(args.photos, encoding='utf-8') as fh:
        photos = json.load(fh)
    if args.skip_adjusted:
        photos = [p for p in photos if not p.get('focus')]
    results, missed, tiles = [], [], []
    for n, p in enumerate(photos, 1):
        try:
            img = load(p['url'])
        except Exception as e:  # noqa: BLE001 - report and carry on
            print('[%d/%d] %s: download failed (%s)' % (n, len(photos), p['name'], e), file=sys.stderr)
            continue
        h, w = img.shape[:2]
        face = best_face(detector, img)
        if face is None:
            missed.append(p['name'])
            print('[%d/%d] %s: no face found' % (n, len(photos), p['name']), file=sys.stderr)
            focus, zoom = '50.0% 50.0%', 1.0
        else:
            focus, zoom = framing(w, h, face)
            results.append({'id': p['id'], 'name': p['name'], 'focus': focus, 'zoom': zoom})
            print('[%d/%d] %s: %s ×%.2f' % (n, len(photos), p['name'], focus, zoom), file=sys.stderr)
        if args.sheet:
            left, top, side = window(w, h, focus, zoom)
            tile = cv2.resize(img[top:top + side, left:left + side], (160, 160), interpolation=cv2.INTER_AREA)
            if face is None:
                cv2.rectangle(tile, (0, 0), (159, 159), (0, 0, 255), 6)
            tile = cv2.copyMakeBorder(tile, 2, 18, 2, 2, cv2.BORDER_CONSTANT, value=(255, 255, 255))
            cv2.putText(tile, ('%d %s' % (p['id'], p['name']))[:24], (3, 175), cv2.FONT_HERSHEY_SIMPLEX, 0.33, (0, 0, 0), 1, cv2.LINE_AA)
            tiles.append(tile)

    with open(args.out, 'w', encoding='utf-8') as fh:
        json.dump(results, fh, ensure_ascii=False, indent=1)
    print('Faces found: %d of %d. %s' % (len(results), len(photos), ('No face: ' + ', '.join(missed)) if missed else ''), file=sys.stderr)

    if args.sheet and tiles:
        cols = 8
        blank = np.full_like(tiles[0], 255)
        tiles += [blank] * (-len(tiles) % cols)
        rows = [np.hstack(tiles[i:i + cols]) for i in range(0, len(tiles), cols)]
        cv2.imwrite(args.sheet, np.vstack(rows))


if __name__ == '__main__':
    main()
