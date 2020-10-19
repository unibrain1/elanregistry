Installation Guide
------------------

1) Install UserSpice

 Download, install, and test UserSpice according to the installation guide.  Make sure it's working before proceeding. https://userspice.com/
 
 The installation has been done using UserSpice 5.1.6
 

2) Install the Elan Registry 
Upload the files.  Note that there are a couple of files that overwrite some of the core UserSpice files.  I've kept this to a minimum

  
3) Extend Database (SQL file pending)
 - TODO create SQL file to extend DB
I should really write a SQL file to do this but I've not done that yet
- profiles table needs City, State, Country attributes
- Create the cars, car_user, car_hist tables
- Create the views (what are they again?)
- Populate with sample data (Again I should have a SQL file for this)
- Load the Elan Registry Configurations (How do I do this??)

4) Setup the environment files
- Part customization gets DB passwords etc from an encyrpted env file.  
- Instructions on how to create are TODO

