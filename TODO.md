# TODO

* [ ] Write unit tests for all endpoints
* [ ] Test authentication against all endpoints
* [ ] Add JSON schema assertions against endpoints

Context maps
    Show additional info like:
        1. Parking locations
        2. Routes
        3. Houses to 'call' in on
        4. Location to drop off money
        5. Walking routes

What would the ui for this look like?
    Have draggable markers with different icons

Priddy:
    Dallimore's
    Welsh's Green
    Cuckoo Cleeves
    Rose Cottage
    Nine Barrows swallet
    Attborough
    Lamb Leer Cavern
Nordrach / Charterhouse:
    Pinetree Pot
    Ubley Warren Pot
    Tynings Barrows Swallet
Other:
    Loxton Cavern
    Upper Canada Cave
Cheddar:
    Reservoir Hole
    Long Hole
    Gough's Cave

Add data for: 
    Home Close Hole
    Spider Hole

Remove/Hide
    Banwell Stalactite Cave


Only 1 of the images successfully uploaded for Bakers pit?

Back nav button from cave page doesn't keep previous search

Add data for:
    Mines we explored in Mid Wales
    South Wales caves

Add concept of collections to database / api

Add and improve data for Mendips
    Add photos of entrances
    Write references for Somerset Underground books

Add a geojson field to each cave_system which can store arbitrary geojson data.
    Use it to show entrance, parking location and walking route. Plus any other info.
        How to do icons for these things?
            Don't go that complex, just have points and lines. Default items

## MVP features
* Show all media for a cave system in its own box
* Write up Mendips using references
    * Add Mendip Underground references
    * Add additional references like Wikipedia where relevant
* Fix exit when going from a through trip to not
* Fix editing a trip and adding new people

* Replace all icons with new logo

## Further work
* cave difficultly?
* Add cancel button to trip editor / creator
* Allow deep links to trip without a login. Useful for sharing.
    * Remove auth
    * Ensure email address is obfuscated on trips and it's just user / club
    * add share button so its easy to post elsewhere
    * ensure people not logged in know what they are missing. 
    * 
    
* Improve styling of Markdown rendering
* Add page so admins can add new caves / cave_systems
* Rename conditions to hazards? Add CO2
* Add Scottish caves to app

* Email people when they're tagged in a trip
    * Use Sendgrid
    * Perform actions sync
    * Send emails to the rest of the trip participants
* Update styling of core pages

* Show parking / walking route options to a cave using GeoJSON shapes. Add editor for this to the cave edit page. https://maplibre.org/maplibre-gl-js/docs/examples/maplibre-gl-terradraw/
    * save as access_geojson
* Render tags differently on cave/[id] page
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

* Add images to systems?
* Add surveys to systems

* When showing multiple entrances in a cave_system, show the distance and direction to those entrances from the currently selected one.

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

* Add concept of routes
    Routes have:
        * system
        * tags
        * difficulty
        * description
        * media
