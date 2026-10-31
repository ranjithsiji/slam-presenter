# Science Slam Presenter Profiles

WordPress plugin for scienceslam.in: presenter profiles, slam events (4 regional centres + the final) and timetables.

## Repository layout

- [`slam-presenter-profiles/`](slam-presenter-profiles/): the WordPress plugin (zip this folder to install it).
- [`slam-presenter-profiles/tools/`](slam-presenter-profiles/tools/): WP-CLI helpers and the photo face-centering script.
- [`examples/sample-presenters.csv`](examples/sample-presenters.csv): a Google Form responses export in the format *Import / Export* expects (same column headers, made-up presenters).
- [`examples/sample-timetable.txt`](examples/sample-timetable.txt): the timetable text format accepted by *Import from text* and *Add New Timetable → Paste timetable text* (sample names).

The two examples belong together: import the CSV into a slam, then paste the sample timetable for it, and every talk matches a presenter.

## Editions

Slams belong to an **edition**: one year's set of regional slams and the final, eg. *Kerala Science Slam 2026*. *Science Slam → Editions* lists every edition (slams, presenters, dates). Add a new edition there and click it to set it up: number of regional slams with name, date, venue, city and talk slots, and the final. Saving creates those slams as drafts with empty timetables.

Admin screens (Presenters, Presenter Table, Timetable, Photos, Import/Export, Slam Events) work on one edition at a time: the **Working on** bar at the top switches it (remembered per user; the default is set in Settings or with *Make default* on Editions). Presenters not yet in any slam show in every edition. The public website is not filtered.

## Workflow for a centre

1. **Science Slam → Import / Export**: choose the slam to import into, then upload the Google Form responses CSV. Columns are matched automatically (check them), venue values become a Slam Event. Re-importing the same sheet updates presenters (matched by English name within the event) instead of duplicating them.
2. **Slam Events**: set date, venue, city, type (regional / final) and the banner text shown on profiles. *Slam page* is the slam's own page (eg. the region page): the slam name on profiles and the date/venue line on the timetable link to it. *Schedule page* is where the profile's "full schedule" button goes; leave it blank and the page containing that event's `[slam_timetable]` is used. Rename the slug if needed. It is what the shortcodes use, eg. `ernakulam`.
3. **Photos**: save each slam's Drive photo folder in its event details (*Google Drive photo folder*) and share it as *Anyone with the link → Viewer*. On *Photos*, choose the slam, then *Fetch missing photos* (only that slam's presenters). A presenter's Drive link can be added or corrected on their card (*Edit link → Save & fetch*), eg. for new people in the final. If sharing is not possible, download the folder from Drive as a ZIP and upload it on the same page. Files are matched by the presenter name in the file name.
4. **Edit a presenter** (Presenter Table → name, or the *Edit profile* button on the profile page when logged in): bio, designation, social links (incl. Wikidata), papers, video, award.
5. **Crop photos**: in the *Photo & crop* box, or *Photos → Adjust* for quick passes over many presenters. Drag to move, scroll or use the slider to zoom; the square is exactly what the website shows. The original upload is never changed (the crop is stored as a face point + zoom), and a newly fetched photo starts from the default crop.
   For a first pass on many photos, [`slam-presenter-profiles/tools/face-center.py`](slam-presenter-profiles/tools/face-center.py) detects faces and sets the crop automatically (see the script header); crops already adjusted by hand are kept.
6. **Timetable**: the screen lists every timetable (type, date, slots filled, day) with *Edit timetable* / *View*. Open one, then either *Auto-generate*, paste the circulated timetable text into *Import from text*, or build by hand (drag rows, click presenters in "Not yet scheduled"). Times recalculate from the durations; set a *fixed start* on a row to force a time (eg. after lunch). To change who is in a slot, click the presenter name: the picker searches names, institutions and titles, and shows *Unscheduled*, *This event* or *All presenters*. Choosing someone who already has a slot swaps the two. Save.
7. Put the shortcodes on the event page.

**Publishing**: each timetable is *Published* or *Draft* (Timetables list, builder, or event details). Visitors do not see a draft: its page shows a "coming soon" note (Settings) and profiles hide that slam's time and schedule button; editors see it with a draft notice. New timetables start as drafts. Changes clear WP Super Cache.

**Talk slots** can be changed any time: the *Talk slots* box in the builder, the field in the event details, or *+ Talk* / × on a row. Fewer slots only removes empty ones.

**New timetable** (eg. the final): *Timetable → Add New Timetable* (asks for the number of talk slots). Enter the event (name, short name for shortcodes, type, date, venue, banner text, start time) and start from a blank day, a copy of another event's day layout (sessions, breaks and empty talk slots) or pasted timetable text. Fill talk slots with the presenter picker (*All presenters* lists everyone), or for a final use **Select finalists…**: a popup with every regional slam's presenters side by side to tick and add in one go (empty slots are filled first). Saving adds the scheduled presenters to the event, so for a final their profile banner shows the final.

## Shortcodes

| Shortcode | Output |
| --- | --- |
| `[slam_timetable event="ernakulam"]` | Full day timetable, talks link to profiles. `photos="no"` hides avatars. |
| `[slam_presenters event="ernakulam"]` | Photo grid. `columns="4"`, `order="timetable"` or `"name"`, `show="title"` or `""`. |
| `[slam_talks event="final"]` | Numbered table of talks (as on the 2025 profiles). Not shown on profiles; use it on an event page if wanted. |
| `[slam_presenter id="123"]` | One profile card anywhere. |

`event` accepts the event slug or term ID.

## Data model

- Post type `slam_presenter`, at `/presenter/<english-name>/` (base configurable in Settings). Title is the display name (Malayalam), content is the bio, photo is the featured image.
- Meta (`_ssp_*`): `name_en`, `institution`, `designation`, `talk_title`, `summary`, `abstract`, `award`, `video_url`, `photo_drive_url`, `photo_focus` (face point, "x% y%" of the photo), `photo_zoom`, `email` (+ `email_public`), `phone` (never shown), `form_no`, `social` (array by network), `papers` (array of title/authors/venue/year/url).
- Taxonomy `slam_event` (no public archive). Term meta `ssp_event_type`, `ssp_event_date`, `ssp_venue`, `ssp_city`, `ssp_label`, `ssp_sort_order`, and `ssp_schedule` (the timetable: start time plus ordered items of type session / talk / break / item with durations).

## Notes

- The profile card replaces the theme's title, date and featured image on presenter pages (Settings → "Hide the theme's page title…"). Tested with Twenty Twenty-Four; Astra's title is turned off through `astra_the_title_enabled`.
- Front-end labels (സംഗ്രഹം, അവതാരകരെക്കുറിച്ച്, മുഴുവൻ സമയക്രമം കാണുക, …) are editable in Settings.
- The profile's schedule button links to `<schedule page>#ssp-talk-<id>`, which scrolls to and highlights that presenter's row.
- Colours come from Astra's global colour variables when present (`--ast-global-color-*`), so they follow the live site's palette.

## License

Copyright (C) 2026 Ranjithsiji

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. See [LICENSE](LICENSE) for the full text.
