=== Science Slam Presenter Map ===
Requires at least: 6.2
Requires PHP: 7.4
License: GPL-2.0-or-later

A map of where the Kerala Science Slam presenters come from. Shortcode: [slam_presenter_map]  ([slam_presenter_map list="no"] hides the institution list; [slam_presenter_map finalists="yes"] shows only the finalists.)

* Presenters: the talks of each regional slam's published timetable (final events are skipped, so finalists are not counted twice); with finalists="yes", the presenters in each regional slam's published result in "Science Slam Presenter Profiles" (presenter institution: the Institution field).
* Places: data/presenter-places.json. A presenter goes to the first place whose "match" text appears in their institution; editors see anyone not placed yet under the map. Add a place there for a new institution.
* Boundaries: data/kerala-tamilnadu.geojson (DataMeet, CC BY 2.5 India, from the Wikidata/Wikimedia Commons geoshapes).
* Map tiles: OpenStreetMap (sent with the site as Referer, as the OpenStreetMap tile policy requires). Leaflet 1.9.4 (BSD-2-Clause) is bundled.
* The data is cached for 10 minutes and refreshed whenever a presenter or a slam is saved.
