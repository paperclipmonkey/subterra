# TODO

## MVP features
* Keep app database alive without sleeping
* Write up Mendips using references
    * Add Mendip Underground references
* Add quick done 'tick' option for any cave.
    * Add concept of trip which doesn't have a datetime associated with it. 
    * These can be added automatically when you set a cave as 'done' from the cave page.
* Complete trips I've done on Mendip
* Add Scottish caves to app
* Fix exit when going from a through trip to not

* Replace all icons with new logo

## Further work

* Add cancel button to trip editor / creator
* Allow deep links to trip without a login. Useful for sharing.
    * Remove auth
    * Ensure email address is obfuscated on trips and it's just user / club
* Add healthcheck endpoint (Call it during deployment)
* Switch over to using slugs for urls in pages
    * Ensure DB is unique for slugs
    
* Improve styling of Markdown rendering
* Add page so admins can add new caves / cave_systems
* Rename conditions to hazards? Add CO2

* Add updating entrance photo
* Show either hero_image or entrance_image on cave page

* Add good data seeders for local development
* Document generating Google auth details in setup
* Add feature tests

* Email people when they're tagged in a trip
    * Use Sendgrid
    * Perform actions sync
    * Send emails to the rest of the trip participants
* Update styling of core pages
* Add Tracestrack maps using MapLibre GL JS.

* Show parking / walking route options to a cave using GeoJSON shapes. Add editor for this to the cave edit page. https://maplibre.org/maplibre-gl-js/docs/examples/maplibre-gl-terradraw/
    * save as access_geojson
* Render tags differently on cave/[id] page
    * Show region as part of slug in ui
    * Show access as an icon
* Add ability for tags to be individual or multi (from database)
    * Add DB migration for ability
    * Draw ui based on value
    * Filter with any or explicit based on groups

* Add graphs to the profile page. Ideas for graphs:
    * Trip difficulty
    * Trip duration
    * Average trip participants
    * Location heatmap
    * Github-like commit history calendar for trips (Monday-Sunday)

Add tag collection page
    Collections page show:
        1. tags
        2. Curated
        3. My collections

* Find CC photos to use for entrances
* Add images to entrances
* Add images to systems
* Add surveys to systems

* When showing multiple entrances in a cave_system, show the distance and direction to those entrances from the currently selected one.

* Add explicit media section to cave/[id] page
    * Pulls files from the media table as well as from all trips

What would the relationship look like?
    * media table
    * media_cave_system
    * media_cave
        media_id
        cave_id
        type (survey, entrance, photo)

* Automatically add current user to any trip they add
    * Stop from removing themselves in the ui?
        * Add a listener for participants so that if they remove themselves they get added back in.
    * Remove yourself from the users list.

* Add 'media' to caves
    * Type
        (photo)
        (video)
* Add ability to add a cave in a new page

Add grid/list/map view icons for caves list
    * Grid can show photo and more info like access

* Add ability to choose cave_system from dropdown when editing a cave.

Order users api response by frequency of use.

* Remove new trip page from history when we move forward

* Add functionality of distance from me to caves search?

* Add concept of routes
    Routes have:
        * system
        * tags
        * difficulty
        * description
        * media
