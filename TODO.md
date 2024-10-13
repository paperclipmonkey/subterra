# TODO
* Add filters section to caves list page
    * Add any of the filters
        * Add tag groups
            * Location
            * Challenges
            * Access
            * Length (>1km)
            * Depth
            * Tackle required
* Add profile endpoints
    * /me
* Run migration to add cascade
* Add way of getting trips for a user (filtering on trip endpoint?)
    * Add list of recent trips they've been on to their profile
* Editing a trip
* Support uploading photos for a trip? Tigris
* Add real trip with Oli and Alaisdair
* Add global scope for public and private trips
* Add hidden pages for adding new cave systems / caves
* Add signup with Google support
    * Add google developer account
    * Add support inside Laravel with socialite
    * Add signup button inside webapp
* Add Register / login page when the user isn't currently signed up
* Add support for 'collections'. Lists of caves / cave_systems that you can browse
    * Title
    * Description
    * Caves
    e.g. Top 10 Mendip caves every fresher has to do
         Hardest trips in Yorkshire
         Ladders ladders everywhere
    How would a data model for this work? You can favourite a list (A user then has favourites?)
    And then track your progress through that list?

View the profile of other club members

* Add ability to click between system entrances from cave system view
* Add functionality of distance from me to caves search?
* Remove tag look from things that aren't tags
* Add tags view to list of caves in response
* Add view customisation to list of caves?
* Add tag search to caves list
* Add ability to switch between list and map view (under search bar?)
* Add loading animation for pages
* Add access info for cave entrances (use icons) New table? Or better to do them as tags?
* Support resizing photos with image intervention
* Add additional info about access to entrances
* Support search / filtering of caves list (system, region, access)

* Improve design of cave page

* Add top navigation bar

* Add caves around the Stump as examples

* Add concept of caving regions

* Add Google Login for signups https://medium.com/@mimranisrar6/how-to-add-a-google-login-using-socialite-in-laravel-21f6eebafcec

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

* Add slug to caves / cave_system
* Make searches bookmarkable
* Add cave system name to trip url

Generate stats for individuals so you can see their recent trips and stats
    * Graph of number of trips they've been on?
    * Cumulative number of hours underground in the previous year
        * Give them a moniker based on this. (troglodyte, etc)