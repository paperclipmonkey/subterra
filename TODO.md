# TODO
What is MVP
* Create a nice logo for the site
* Edit a trip
    * Fix JS data structure
    * Add PUT support to Controller

* Add permissions so you can only edit trips you were a participant on

* Require manual approval of signups after logging in for the first time (New field in Users?, email_verified_at / has_signed_up)

* Go to profile page / introduce system once a user has signed in for the first time. 
    * Get them to add their club

* Automatically add current user to any trip they add
    * Stop from removing themselves in the ui?
        * Add a listener for participants so that if they remove themselves they get added back in.
    * Remove yourself from the users list.

* Give info about what to do if there's no items in "My Trips" list.

* Add 'info' page which describes what the system is and how to give feedback


# Filtering of caves functionality
List of tags comes from api endpoint
Click Apply to return to the previous page with the search enabled
Filtering is done client side against caves and systems
Keep search and tags between page navigations
Add button to clear all filters
Add image and description for each tag option so we can nicely list caves with that tag. Ala collections.
    This would enable us to have a page for each region, for example

* Add 'media' to caves
    * Type
        (photo)
        (video)
* Add ability to add/edit a cave in a new page?

Order users api response by frequency of use.

Add grid/list/map view icons for caves list
    * Grid can show photo and more info like access

* Add frontend for creating caves in a UI with rich text editor for Markdown
    * Choose cave system from dropdown, or create if it doesn't already exist.
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

Talk to student cavers about their needs