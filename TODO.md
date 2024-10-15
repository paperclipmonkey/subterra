# TODO
What is MVP
* Create an initial page which just shows the logo and a login button
    * Require manual approval of signups after logging in for the first time (New field in Users?, email_verified_at / has_signed_up)
    * List functionality on 'homepage'
    * Add Login with google button to the 'homepage'
* Create an account
    * Register
    * Add trips
        * Default to being on trips you add
    * See your own profile
        * Support logging out
* Edit a trip
    * Fix JS data structure
    * Add PUT support to Controller

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
* Add way of getting trips for a user (filtering on trip endpoint?)
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
