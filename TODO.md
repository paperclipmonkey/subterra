# TODO
What is MVP
* Show trip time nicely formatted in list
* Add validation so cave_system_id is required in the database
* Redirect to login page when the user isn't authenticated
* Hide menu bar when user isn't authenticated
* Create an account
    * Add trips
    * Default to being on trips you add
    * See your own profile
* Edit a trip
    * Fix JS data structure
    * Add PUT support to Controller
* Hide map functionality

* Automatically add current user to any trip they add
    * Stop from removing themselves in the ui?
* Add ability to tag people as on a trip which aren't members yet
* Require manual approval of signups after logging in for the first time (New field in Users?, email_verified_at / has_signed_up)

* Add 'news' functionality
    * Read table of news articles written in Markdown
    * Render them to a page

* Write some data seeders to create some sample caves
* Add frontend for creating caves in a UI with rich text editor for Markdown
* Remove new trip page from history when we move forward
* Add filters section to caves list page
    * Add any of the filters
        * Add tag groups
            * Location
            * Challenges
            * Access
            * Length (>1km)
            * Depth
            * Tackle required

* Add way of getting trips for any user (filtering on trip endpoint?)
    * Add list of recent trips they've been on to their profile
* Add real trip with Oli and Alaisdair
* Add global scope for public and private trips
* Add endpoint for CRUD cave_system
* Add contraint for cave always having cave_system
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

* Add functionality of distance from me to caves search?
* Add tag search to caves list
* Add ability to switch between list and map view (under search bar?)
* Add loading animation for pages
* Add access info for cave entrances (use icons) New table? Or better to do them as tags?
* Add additional info about access to entrances
* Support search / filtering of caves list (system, region, access)

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
