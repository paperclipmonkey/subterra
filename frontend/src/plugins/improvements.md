# Improvements
1. Add a 'more' or additional menu to the bottom navigation which brings up a page showing additional functionality.
    1. Show 'huts'. This will list clubs that have huts and their locations / amenities. Show in either a map or grid view. 
        1. A hut should have a name, location, and a list of amenities, which club owns it, whether others can book it out, a link to more info, photos, and from the hut a list of close cave entrances. Also store / show which clubs this hut is reciprocal rates with. That helps lower the cost.
    2. 'collections'. This will show a list of collections of caves, and allow you to view them. Your progress through ticking off the caves is noted in the ui. 
        1. An example would be, top 10 longest caves on The Mendips.
        2. Another would be Hardest caves in the UK.
        3. For collections they should have a name, description, photo, and a list of caves in the collection. 
        4. Collections should be editable by admins. The admins should be able to add caves to collections and remove caves from collections as well as editing the photo, name and description.

2. Improve the funcionality of adding a new cave and cave system to the app.
    1. Start with adding a cave system. Once the system has been created it should be possible to add 'caves' to it.

3. Improve the ability to join multiple cave systems together in the ui if they had been accidentally added without.
    1. From the main system, add the ability to 'join' to another cave system. This should delete the other cave system and add any caves associated to it to this one.
    2. The length and depth should not be added together, but the descriptions should be appended with a --- to separate them on new lines.

4. Information about access for stuff like Charterhouse Caving Company should link through to more info about CCC. There should be perhaps info about different access requirements which work as standallone pages. E.g. SWCC and their access for OFD.
    1. These pages should be editable by an admin in a list from the admin page.
    2. They should have a name, description (as Markdown) and a date when they were last edited.

5. Show / store last edited date for cave data. Perhaps keeping and/or showing a list of changes made in the past.

6. Add functionality for users to be cave admins? They should not have access to the rest of the admin interface but can edit information about caves.
    1. Show missing data and tasks to complete. Every cave should have at least 1 photo (of the entrance) and 4 tags. For any user allow them to submit a photo of the entrance as Trip media. 
    2. Missing references. There should be at least two references for each cave.
    3. Missing length / depth.
7. Add better functionality for importing caves from registry data. With a scrape it should be possible to import it and then fix up the data in the u.i.



Add functionality for leaving a callout with Subterra. This could live in the 'more' section. It takes similar information to the new trip form, including who, where, what and when.
    1. Advice should be given as to what a callout is meant to protect against and to not abuse the system.
    2. Give information about who is 'on-call' during the callout time they're asking for.
    3. Submit the callout request and ensure it's been saved correctly before showing the user.
    4. When a user submits a callout request, text them comfirming it's active and to Reply with "SAFE" when they're out.
    5. Also email the user with the same info.
    6. Also text / email every other trip participant added to the trip.
    7. Add functionality to cancel the callout using a short code from a text number or by clicking a link in the email and confirming.
    8. If the callout is not cancelled 15 mins before then a text / email should go out to the trip participants to warn them of the callout being triggered.
    9. When it is triggered a text / Slack / Email should go out to all admins which are on call. One of them will then go into the admin portal and mark the incident as managed.
    10. The manager will then make notes against the 'incident' until it's finished and can be marked as resolved. Resolved callouts are kept for 30 days.
    11. Cancelled callouts are deleted immediately.
    12. Calendar functionaltiy should be created so admins can set their schedule for when they're on-call. If no one is on-call during their callout time then the callout can't be created.
