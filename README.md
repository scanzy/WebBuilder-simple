# WebBuilder-simple
Simple tool to assemble a website (layout + contents) stored in different files 

## Why Builder?
It's very common for a website to have common layouts across pages.
It can be very useful to store layouts and contents in separated files. 
WebBuilder-simple allows you to quickly assemble layouts and contents every time you edit layouts or contents, with ONE CLICK.

### WebBuilder-simple concept
WebBuilder-simple is some kind of an automatic find-replace tool.
You need to write your layouts with custom placeholders (e.g. @@contents@@) and then configure builder to replace your placeholder with other content, stored in separated files

## Usage
1. Download WebBuilder-simple files and place them in some folder on your server
2. Protect that folder with password so only admins can access it
3. Visit builder.php page with your browser
4. Add files to be assembled specifying destination path
5. Configure a layout file specifying source layout file path
6. Configure your substitutions, specifying placeholder, content to replace or the file with content to replace
7. Click "Build now", and every time you edit some files you ONLY have to click "Build now" to have every page updated

### Contributing
You are free to fork, download or use this code
I would be very happy to receive any type of suggestion
If you find some, please report bugs opening an issue

### Note on previous versions
This readme is related only to builder v5, previous versions have a slightly different concept.
Also, previous versions are buggy and they have no UI, so I strongly recommend using v5.

## Enjoy!! 
