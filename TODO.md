# TODO
* [ ] Make trip description pages public
* [ ] Fix adding new users to a trip

* [ ] Test authentication against all endpoints
* [ ] Add JSON schema assertions against endpoints

Continue to show filters in ui after page reload

Add feature to add new caves and cave systems. Explain the differences in admin ui.

Hide emails from API wherever they're found

Allow users to add a username / Nickname?

Context maps
    Show additional info like:
        1. Parking locations
        2. Routes
        3. Houses to 'call' in on
        4. Location to drop off money
        5. Walking routes

What would the ui for this look like?
    Have draggable markers with different icons


Remove/Hide
    Banwell Stalactite Cave, Viaduct Sink

Back nav button from cave page doesn't keep previous search. Do back nav instead?

Add concept of collections to database / api

Add a geojson field to each cave_system which can store arbitrary geojson data.
    Use it to show entrance, parking location and walking route. Plus any other info.
        How to do icons for these things?
            Don't go that complex, just have points and lines. Default items

## MVP features
* Fix exit when going from a through trip to not
* Fix editing a trip and adding new people

* Replace all icons with new logo

## Further work
* Add cancel button to trip editor / creator
* Allow deep links to trip without a login. Useful for sharing.
    * Remove auth
    * Ensure email address is obfuscated on trips and it's just user / club
    * add share button so its easy to post elsewhere
    * ensure people not logged in know what they are missing
    
* Rename conditions to hazards? Add CO2
* Add Scottish caves to app

* Show parking / walking route options to a cave using GeoJSON shapes. Add editor for this to the cave edit page. https://maplibre.org/maplibre-gl-js/docs/examples/maplibre-gl-terradraw/
    * save as access_geojson
* Render tags differently on cave/[id] page
    * Show access as an icon
* Add ability for tags to be individual or multi (from database)
    * Add DB migration for ability
    * Draw ui based on value
    * Filter with any or explicit based on groups

Add tag collection page
    Collections page show:
        1. tags
        2. Curated
        3. My collections

* Automatically add current user to any trip they add
    * Stop from removing themselves in the ui?
        * Add a listener for participants so that if they remove themselves they get added back in.
    * Remove yourself from the users list.

Order new trip users api response by frequency of use.
