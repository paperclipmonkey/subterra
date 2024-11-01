# TODO
* Migrate data from local to fly.io database
    * Use postgres dump?
What is MVP
* Edit a trip
    * Fix JS data structure
    * Add PUT support to Controller
* Add permissions so you can only edit trips you were a participant on
    Limit ui to only show button if you were a participant
* Add concept of 'admin' to the database where they can edit caves
* Require manual approval of signups after logging in for the first time (New field in Users?, email_verified_at / has_signed_up)
* Switch over to using slugs for urls in pages
* References
    * From cave_system
        * What do we care about with references?
            Type: (web, book etc)
            Reference:
* Add access / entrance info for 10 mendip caves
* Add cave_system info for 10 mendip caves
* Add page so admins can add new caves / cave_systems
* Rename conditions to hazards? Add CO2
* Add done / not done functionality.
    * In cave list return whether the current user has done a trip there
    * Add filter functionality

* Add concept of trip which doesn't have a datetime associated with it. These can be added automatically when you set a cave as 'done' from the cave page.

* Render tags differently on cave/[id] page
    * Show region as part of slug in ui
    * Show access as an icon
* Add ability for tags to be individual or multi (from database)
    * Add DB migration for ability
    * Draw ui based on value
    * Filter with any or explicit based on groups

* When showing multiple entrances in a cave_system, show the distance and direction to those entrances from the currently selected one.

Add tag collection page
    Collections page show:
        1. tags
        2. Curated
        3. My collections

* Find CC photos to use for entrances
* Add images to entrances
* Add images to systems
* Add surveys to systems

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
* Add ability to add/edit a cave in a new page?

Order users api response by frequency of use.

Add grid/list/map view icons for caves list
    * Grid can show photo and more info like access

* Add ability to choose cave_system from dropdown when editing a cave.

* Add edit button for cave_system when viewing cave.

* Remove new trip page from history when we move forward

* Add hidden pages for adding new cave systems / caves

* Add functionality of distance from me to caves search?
* Add ability to switch between list and map view (under search bar?)

* Add concept of routes
    Routes have:
        * system
        * tags
        * difficulty
        * description
        * media

Add concept of reference list for 'caves'
    * isbn / title / page number
    * url
