<?php

//builder v5 by ScanzySoftware

//31/01/2015 4h code
//01/02/2015 6h code
//02/02/2015 4h code

//25/02/2015 little bug fix (added chars escaping in js getcontents() and loadfiles())
//30/04/2015 little big fix (added "mb_internal_encoding('UTF-8')" to avoid reading strange chars at the beginning of some html)

define('DATAFILE','builder.dat');

session_start(); //starts session

mb_internal_encoding('UTF-8'); //sets default encoding (to avoid reading strange bytes at the beginning of some files)

//if request from this page (ajax)
if (isset($_GET['action']))
{
    //loads builder if necessary
    if (!isset($_SESSION['builder'])) load();
        else if ($_SESSION['builder'] == NULL) load(); 

    switch($_GET['action'])
    {
        case 'build': buildall(); break; //builds all

        case 'addfile': addfile(); break; //adds a file  

        case 'renamefile': renamefile(); break; //renames a file

        case 'deletefile': deletefile(); break; //removes a file

        case 'renamelayout': renamelayout(); break; //renames the layout

        case 'addsub': addsub(); break; //adds a substitution string

        case 'changesearch': changesearch(); break; //changes string to search

        case 'changereplace': changereplace(); break; //changes string to replace

        case 'toggleisfile': toggleisfile(); break; //toggle isfile mode

        case 'deletesub': deletesub(); break; //deletes a sub
           
        case 'getfiles': getfilesjson(); break; //gets files (in json)

        case 'getcontents': getcontentsjson(); break; //gets contents (in json)
    }
    exit;
}

load(); //called if action is not set

class Builder
{
    var $files = array(); //array of files (class File below)
}

//saves the current state to DATAFILE
function save()
{
    //serializes the object
    $serialized = @serialize($_SESSION['builder']);
    if ($serialized === FALSE)
        error("error while serializing class");            

    //saves the serialized object
    if (@file_put_contents(DATAFILE,$serialized)===FALSE) 
        error("error while saving to ".DATAFILE);
}

//loads the state from DATAFILE
function load()
{
    //checks if exists
    if(!file_exists(DATAFILE))
    {
        //if not initializes a new class
        $_SESSION['builder'] = new Builder();
            
        save(); //and saves it
        //now it's ready to reload the class
    }

    //gets the serialized object
    $serialized = @file_get_contents(DATAFILE);
    if ($serialized===FALSE) 
        error("error while reading from ".DATAFILE);
        
    //unserializes the object
    $_SESSION['builder'] = @unserialize($serialized);
    if ($_SESSION['builder'] === FALSE)
        error("error while unserializing class");         
}   

//returns true if $file is already in builder
function file_exists_in_builder($file)
{
    foreach($_SESSION['builder']->files as $f)
        if ($f->target == $file) return TRUE;
    return FALSE;
}

//returns true if $file is a valide filename
function file_name_valid($file)
{
    if (preg_match("/[^A-Za-z0-9-_.\/]/",$file) == 0) return TRUE;
    return FALSE;
}

//returns true if $file is a valid file index
function file_index_valid($file)
{
    return (count($_SESSION['builder']->files) > $file);
}

//returns true if $sub is a valid sub index
function sub_index_valid($file,$sub)
{
    return (count($_SESSION['builder']->files[$file]->subs) > $sub);
}

//gets the file object that contains contents
function getcontents($file)
{
    return $_SESSION['builder']->files[$file]; 
}

//builds all files
function buildall()
{ 
    clearstatcache(); //clears cache
    
    //foreach file in builder
    foreach($_SESSION['builder']->files as $key => $f)
    {
        if (file_exists($f->target)) //if already exists
        {   //if layout or subs not modified since last build
            if ((filemtime($f->layout) < $f->lastbuild) &&
            ($f->lastfilemodify < $f->lastbuild))
            {
                $flag = TRUE;
                foreach($f->subs as $s)
                    if ($s->isfile) //for subs that have
                        if (filemtime($s->replace) > $f->lastbuild)
                             { $flag = FALSE; break; }
                if ($flag) continue;
            } 
        }
        buildfile($key); //builds it
    }
}

//builds a file
function buildfile($key)
{
    $t = $_SESSION['builder']->files[$key]->target; //gets target name
    $l = $_SESSION['builder']->files[$key]->layout; //gets layout name

    //if layout doesn't exist
    if (!file_exists($l)) error("error while building file '".$t."': layout file '".$l."' not found"); 
    
    $out = @file_get_contents($l); //gets layout content
    if ($out === FALSE) error("error while building file '".$t."': cannot get contents of layout file '".$l."'"); 
    
    foreach($_SESSION['builder']->files[$key]->subs as $s) //performs substitutions
    {
        while(strpos($out,$s->search) !== FALSE) //until every occurrence of search has been substituted
        {
            if ($s->isfile == TRUE) //if the replace string is in file
            {
                //if replace file doesn't exist
                if (!file_exists($s->replace)) error("error while building file '".$t."': replace file '".$s->replace."' not found"); 
                
                $r = @file_get_contents($s->replace); //gets replace content
                if ($r === FALSE) error("error while building file '".$t."': cannot get contents of replace file '".$s->replace."'");  
            }
            else //if the replace string is not in file
                $r = $s->replace; //replace content is already loaded 

            //checks for possible recursion (to prevent infinite looping)
            if (strpos($r,$s->search) !== FALSE) error("String to search '".$s->search."' found in string to replace.\nPlease change your strings to avoid infinite loop substitution");
                
            //replaces the string
            $out = str_replace($s->search,$r,$out); 
        }
    }

    $out = @file_put_contents($t,$out); //writes bytes to file
    if ($out === FALSE) error("error while writing file '".$t."'");

    $_SESSION['builder']->files[$key]->lastbuild = time(); //updates last build time
}

//adds a file in builder
function addfile()
{
    if(!isset($_GET['newname'])) error("Newname parameter not specified"); //checks if parameter not set

    if(!file_name_valid($_GET['newname'])) //checks if filepath is valid
        error("Invalid name. Valid chars are: A-Z a-z 0-9 . / - _"); 

    if (file_exists_in_builder($_GET['newname'])) //checks if file is already in builder
        error("This file is already in builder");

    $newfile = new File(); //creates a new file in builder
    $newfile->target = $_GET['newname'];
    $_SESSION['builder']->files[] = $newfile;
    save();
}

//renames a file in builder
function renamefile()
{
    if(!isset($_GET['file'])) error("File parameter not specified"); //checks if parameter not set
    if(!isset($_GET['newname'])) error("Newname parameter not specified"); //checks if parameter not set

    if (!file_index_valid($_GET['file'])) //checks if file index is correct
        error("Invalid file index");

    if(!file_name_valid($_GET['newname'])) //checks if newname is valid
        error("Invalid name. Valid chars are: A-Z a-z 0-9 . / - _"); 

    if (file_exists_in_builder($_GET['newname'])) //checks if file is already in builder
        error("This file is already in builder");

    //renames the file in builder
    $_SESSION['builder']->files[$_GET['file']]->target = $_GET['newname'];
    save(); //and saves
}

//deletes a file from the builder
function deletefile()
{
    if(!isset($_GET['file'])) error("File parameter not specified"); //checks if parameter not set

    if (!file_index_valid($_GET['file'])) //checks if file is in builder
        error("Invalid file index");

    //deletes the file from builder
    array_splice($_SESSION['builder']->files,$_GET['file'],1);
    $_SESSION['builder']->files = array_values($_SESSION['builder']->files); //reindexes array
    save(); //and saves
}

//renames a layout
function renamelayout()
{
    if(!isset($_GET['file'])) error("File parameter not specified"); //checks if parameter not set
    if(!isset($_GET['newname'])) error("Newname parameter not specified"); //checks if parameter not set

    if (!file_index_valid($_GET['file'])) //checks if index of file to rename is valid
        error("Invalid file index");

    if(!file_name_valid($_GET['newname'])) //checks if newname is valid
        error("Invalid layout name. Valid chars are: A-Z a-z 0-9 . / - _"); 

    //renames the layout in builder
    $_SESSION['builder']->files[$_GET['file']]->layout = $_GET['newname'];
    $_SESSION['builder']->files[$_GET['file']]->lastfilemodify = time(); //saves current time
    save(); //and saves
}

//adds a file in builder
function addsub()
{
    if(!isset($_GET['file'])) error("File parameter not specified"); //checks if parameter not set
    if(!isset($_GET['newname'])) error("Newname parameter not specified"); //checks if parameter not set
    
    if (!file_index_valid($_GET['file'])) //checks if file index is valid
        error("Invalid file index");

    $newsub = new Sub(); //creates a new sub
    $newsub->search = $_GET['newname'];
    $_SESSION['builder']->files[$_GET['file']]->subs[] = $newsub;
    $_SESSION['builder']->files[$_GET['file']]->lastfilemodify = time(); //saves current time
    save();
}

//renames a search string
function changesearch()
{
    if(!isset($_GET['file'])) error("File parameter not specified"); //checks if parameter not set
    if(!isset($_GET['newname'])) error("Newname parameter not specified"); //checks if parameter not set
    if(!isset($_GET['sub'])) error("Sub parameter not specified"); //checks if parameter not set

    if (!file_index_valid($_GET['file'])) //checks if file is found
        error("Invalid file index");

    if (!sub_index_valid($_GET['file'],$_GET['sub'])) //checks if index is valid
        error("Invalid sub index");

    //renames the string in builder
    $_SESSION['builder']->files[$_GET['file']]->subs[$_GET['sub']]->search = $_GET['newname'];
    $_SESSION['builder']->files[$_GET['file']]->lastfilemodify = time(); //saves current time
    save(); //and saves
}

//renames a search string
function changereplace()
{
    if(!isset($_GET['file'])) error("File parameter not specified"); //checks if parameter not set
    if(!isset($_GET['newname'])) error("Newname parameter not specified"); //checks if parameter not set
    if(!isset($_GET['sub'])) error("Sub parameter not specified"); //checks if parameter not set

    if (!file_index_valid($_GET['file'])) //checks if file is found
        error("Invalid file index");

    if (!sub_index_valid($_GET['file'],$_GET['sub'])) //checks if index is valid
        error("Invalid sub index");

    if ($_SESSION['builder']->files[$_GET['file']]->subs[$_GET['sub']]->isfile) //if isfile
        if (!file_name_valid($_GET['newname'])) //chechs if file name valid
            error("Invalid file name. Valid chars are: A-Z a-z 0-9 . / - _"); 

    //renames the string in builder
    $_SESSION['builder']->files[$_GET['file']]->subs[$_GET['sub']]->replace = $_GET['newname'];
    $_SESSION['builder']->files[$_GET['file']]->lastfilemodify = time(); //saves current time
    save(); //and saves
}

//toggles the isfile mode
function toggleisfile()
{
    if(!isset($_GET['file'])) error("File parameter not specified"); //checks if parameter not set
    if(!isset($_GET['sub'])) error("Sub parameter not specified"); //checks if parameter not set

    if (!file_index_valid($_GET['file'])) //checks if file is found
        error("Invalid file index");

    if (!sub_index_valid($_GET['file'],$_GET['sub'])) //checks if index is valid
        error("Invalid sub index");

    //toggles the value (note the esclamation point)
    $_SESSION['builder']->files[$_GET['file']]->subs[$_GET['sub']]->isfile = !$_SESSION['builder']->files[$_GET['file']]->subs[$_GET['sub']]->isfile;
    $_SESSION['builder']->files[$_GET['file']]->subs[$_GET['sub']]->replace = "";
    $_SESSION['builder']->files[$_GET['file']]->lastfilemodify = time(); //saves current time
    save(); //and saves
}

//deletes a file from the builder
function deletesub()
{
    if(!isset($_GET['file'])) error("File parameter not specified"); //checks if parameter not set
    if(!isset($_GET['sub'])) error("Sub parameter not specified"); //checks if parameter not set

    if (!file_index_valid($_GET['file'])) //checks if file is in builder
        error("Invalid file index");

    if (!sub_index_valid($_GET['file'],$_GET['sub'])) //checks if file is in builder
    error("Invalid sub index");

    //deletes the sub from file
    array_splice($_SESSION['builder']->files[$_GET['file']]->subs,$_GET['sub'],1);
    $_SESSION['builder']->files[$_GET['file']]->subs = array_values($_SESSION['builder']->files[$_GET['file']]->subs); //reindexes array
    $_SESSION['builder']->files[$_GET['file']]->lastfilemodify = time(); //saves current time
    save(); //and saves
}

//gets files (array in json)
function getfilesjson()
{
    echo json_encode($_SESSION['builder']->files);
}

//gets contents (array in json)
function getcontentsjson()
{
    if(!isset($_GET['file'])) error("File parameter not specified"); //checks if parameter not set

    if (!file_index_valid($_GET['file'])) //checks if file is in builder
        error("Invalid file index");

    echo json_encode(getcontents($_GET['file']));
}

//info about a file (to preocess)
class File 
{
    var $target = ""; //file output path
    var $layout = ""; //layout file (source file)
    var $subs = array(); //array of Subs
    var $lastbuild = 0; //last build datetime
    var $lastfilemodify = 0; //last time some sub or layout was modified
}

//info about a substitution
class Sub
{
    var $search = ""; //string to search
    var $replace = ""; //string to replace
    var $isfile = FALSE; 
    //if set to true $replace will contain the path of file whose content will be the replace string
}

//called on error
function error($msg)
{
    echo $msg;
    exit;
}

?>

<html>
<head>
    <title>Builder v5</title>
    <style>
        body { background-color:#0366FF; }
        h2 { background-color:#4499FF; }
        td { background-color:#0C44DD; }
        tr:hover td { background-color: #0500BB; }
        tr.selected td { background-color: #1D0066; }
        h1, h2, td { color:white; }
        
        body, h1, h2, #footer { margin:0; }
        table { margin: 0 auto; border-spacing:0; }
        table { width: 100%; max-width: 100%;}
        td.wrap { width: 1%; white-space: nowrap; }
        td.main { text-align: left; padding-left: 40px; }

        h2 { z-index: 1; }
        body, h2 { position: relative; }

        h1, h2 { width:98%; }
        h1, h2, td { padding:1%; }

        h1, td.main { font-size:25px; }
        h2, h1 a { font-size:30px; }
        td { font-size:20px; }
        
        h1 span, td.main { font-weight: bold; }

        #files, h1, h2, td { text-align:center; }  

        h2, table { box-shadow: 0px 0px 15px black; }

        h1 span { font-size: 60px; text-shadow: 0px 0px 20px black; }
        h1 a { margin-left:80px; }
        a:hover { text-decoration:underline; cursor:pointer; }

        body { min-height: 95%; }
        #footer { position: absolute; bottom: 0px; height: 40px;}
        #push { height: 60px; }

    </style>
    <script>
        function build(element)
        {
            //build started (changes button text)
            element.innerHTML = "Building...";

            //sends request
            xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function()
            {
                if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                {
                    //build finished (restores button)
                    if(xmlhttp.responseText != "")
                    {
                        alert(xmlhttp.responseText); //if errors
                    }
                    element.innerHTML = "Build now";
                    alert("Build successful");
                    loadfiles(); //restores file list
                }
            }
            xmlhttp.open('GET', 'builder.php?action=build', true);
            xmlhttp.send();
        }

        function addfile()
        {
            //asks filename
            newname = window.prompt("Path of file to add: (use forward slashes / for directories)", "");
            if((newname != null) && (newname != ""))
            {
                //sends request to add the file
                xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function()
                {
                    if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                    {
                        if(xmlhttp.responseText == "") //if everything ok
                            loadfiles(); //reloads files
                        else alert(xmlhttp.responseText); //else displays the error
                    }
                }
                xmlhttp.open('GET', 'builder.php?action=addfile&newname=' + encodeURIComponent(newname), true);
                xmlhttp.send();
            }
        }

        function renamefile(file, filename, e)
        {
            e.stopPropagation(); //prevent onclick event on parent element

            //asks filename
            newname = window.prompt("New name: (use forward slashes / for directories)", filename);
            if((newname != null) && (newname != "") && (newname != filename))
            {
                //sends request to add the file
                xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function()
                {
                    if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                    {
                        if(xmlhttp.responseText == "") //if everything ok
                            loadfiles(); //reloads files
                        else alert(xmlhttp.responseText); //else displays the error
                    }
                }
                xmlhttp.open('GET', 'builder.php?action=renamefile&file=' + file + "&newname=" + encodeURIComponent(newname), true);
                xmlhttp.send();
            }
        }

        function deletefile(file, filename, e)
        {
            e.stopPropagation(); //prevent onclick event on parent element

            if(confirm("Are you sure to delete file '" + filename + "'? This cannot be undone"))
            {
                //sends request to add the file
                xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function()
                {
                    if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                    {
                        if(xmlhttp.responseText == "") //if everything ok
                        {
                            loadfiles(); //reloads files
                            document.getElementById("contents").innerHTML = "";
                        }
                        else alert(xmlhttp.responseText); //else displays the error
                    }
                }
                xmlhttp.open('GET', 'builder.php?action=deletefile&file=' + file, true);
                xmlhttp.send();
            }
        }

        function renamelayout(file, layout)
        {
            //asks filename
            newname = window.prompt("Layout name: (use forward slashes / for directories)", layout);
            if((newname != null) && (newname != "") && (newname != layout))
            {
                //sends request to add the file
                xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function()
                {
                    if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                    {
                        if(xmlhttp.responseText == "") //if everything ok
                            getcontents(file); //reloads contents
                        else alert(xmlhttp.responseText); //else displays the error
                    }
                }
                xmlhttp.open('GET', 'builder.php?action=renamelayout&file=' + file + "&newname=" + encodeURIComponent(newname), true);
                xmlhttp.send();
            }
        }

        function addsub(file)
        {
            //asks filename
            newname = window.prompt("String to search:", "");
            if((newname != null) && (newname != ""))
            {
                //sends request to add the file
                xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function()
                {
                    if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                    {
                        if(xmlhttp.responseText == "") //if everything ok
                            getcontents(file); //reloads contents
                        else alert(xmlhttp.responseText); //else displays the error
                    }
                }
                xmlhttp.open('GET', 'builder.php?action=addsub&file=' + file + '&newname=' + encodeURIComponent(newname), true);
                xmlhttp.send();
            }
        }

        function changesearch(file, sub, search)
        {
            //asks new name
            newname = window.prompt("String to search:", search);
            if((newname != null) && (newname != "") && (newname != search))
            {
                //sends request to add the file
                xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function()
                {
                    if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                    {
                        if(xmlhttp.responseText == "") //if everything ok
                            getcontents(file); //reloads contents
                        else alert(xmlhttp.responseText); //else displays the error
                    }
                }
                xmlhttp.open('GET', 'builder.php?action=changesearch&file=' + file + "&newname=" + encodeURIComponent(newname) + "&sub=" + sub, true);
                xmlhttp.send();
            }
        }

        function changereplace(file, sub, replace, isfile)
        {
            //asks new name
            if(isfile) newname = window.prompt("File that contains text to replace: (use forward slashes / for directories)", replace);
            else newname = window.prompt("String to replace:", replace);
            if((newname != null) && (newname != "") && (newname != replace))
            {
                //sends request to add the file
                xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function()
                {
                    if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                    {
                        if(xmlhttp.responseText == "") //if everything ok
                            getcontents(file); //reloads contents
                        else alert(xmlhttp.responseText); //else displays the error
                    }
                }
                xmlhttp.open('GET', 'builder.php?action=changereplace&file=' + file + "&newname=" + encodeURIComponent(newname) + "&sub=" + sub, true);
                xmlhttp.send();
            }
        }

        function toggleisfile(file, sub)
        {
            //sends request to add the file
            xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function()
            {
                if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                {
                    if(xmlhttp.responseText == "") //if everything ok
                        getcontents(file); //reloads contents
                    else alert(xmlhttp.responseText); //else displays the error
                }
            }
            xmlhttp.open('GET', 'builder.php?action=toggleisfile&file=' + file + "&sub=" + sub, true);
            xmlhttp.send();
        }

        function removesub(file, sub)
        {
            if(confirm("Are you sure to delete this substitution string?\n This cannot be undone"))
            {
                //sends request to add the file
                xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function()
                {
                    if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                    {
                        if(xmlhttp.responseText == "") //if everything ok
                            getcontents(file); //reloads contents
                        else alert(xmlhttp.responseText); //else displays the error
                    }
                }
                xmlhttp.open('GET', 'builder.php?action=deletesub&file=' + file + '&sub=' + sub, true);
                xmlhttp.send();
            }
        }
    </script>
    <script>
        window.onload = loadfiles;

        function loadfiles()
        {
            //loads data
            xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function()
            {
                if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                {
                    //puts data into page
                    try
                    { //tries to convert from json
                        files = JSON.parse(xmlhttp.responseText);
                        html = "<table><tbody>";
                        if(files != null)
                            for(var i = 0; i < files.length; i++) //and writes each file
                            {
                                d = new Date(files[i].lastbuild * 1000);
                                html += "<tr><td class='wrap'>Last build:</td><td class='wrap'>" + formatteddatetime(d) + "</td>"
                                html += "<td class='main'>" + files[i].target.escapeHTML() + "</td>";
                                html += "<td class='wrap'><a onclick='select(this);getcontents(" + i + ")'>Select</a></td>";
                                html += "<td class='wrap'><a onclick='renamefile(" + i + ",\"" + files[i].target + "\",event);'>Rename</a></td>";
                                html += "<td class='wrap'><a onclick='deletefile(" + i + ",\"" + files[i].target + "\",event);'>Delete</td></a></tr>";
                            }
                        html += "<tr><td/><td/><td class='main'><a onclick='addfile()'>Add file...</td><td/><td/><td/></tr></tbody></table>";
                        document.getElementById("files").innerHTML = html;
                    } catch(e)
                    { //if response is not json
                        if((xmlhttp.responseText != "null") && (xmlhttp.responseText != "[]")) //if not empty array
                            alert(xmlhttp.responseText); //if error
                        document.getElementById("files").innerHTML = "<table><tbody><tr><td/><td/><td class='main'><a onclick='addfile()'>Add file...</td><td/><td/><td/></tr></tbody></table>";
                        document.getElementById("contents").innerHTML = "";
                    }
                }
            }
            xmlhttp.open('GET', 'builder.php?action=getfiles', true);
            xmlhttp.send();
        }

        function getcontents(file)
        {
            //sends request and retrieves data
            xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function()
            {
                if((xmlhttp.readyState == 4) && (xmlhttp.status == 200))
                {
                    //puts data into page
                    try
                    { //tries to convert from json
                        contents = JSON.parse(xmlhttp.responseText);

                        html = "<h2>Contents:</h2><table><tbody><tr><td/><td class='wrap'>Layout:</td><td class='main'>" + contents.layout.escapeHTML() + "</td>";
                        html += "<td class='wrap'><a onclick='renamelayout(" + file + ",\"" + contents.layout + "\")'>Change</a></td><td/></tr>";

                        if(contents.subs != null)
                            if(contents.subs.length > 0)
                                for(var i = 0; i < contents.subs.length; i++) //and writes each file
                                {
                                    html += "<tr><td/><td class='wrap'>Search:</td><td class='main'>" + contents.subs[i].search.escapeHTML() + "</td>";
                                    html += "<td class='wrap'><a onclick='changesearch(" + file + "," + i + ",\"" + contents.subs[i].search + "\");'>Change</a></td>";
                                    html += "<td class='wrap'><a onclick='removesub(" + file + "," + i + ");'>Remove</a></td></tr>";
                                    html += "<tr><td class='wrap'><a onclick='toggleisfile(" + file + "," + i + ");'>" + (contents.subs[i].isfile ? "(file)" : "(string)") + "</a></td>";
                                    html += "<td class='wrap'>Replace:</td>";
                                    html += "<td class='main'>" + contents.subs[i].replace.escapeHTML() + "</td>";
                                    html += "<td class='wrap'><a onclick='changereplace(" + file + "," + i + ",\"" + contents.subs[i].replace + "\"," + contents.subs[i].isfile + ");'>Change</a></td><td/></tr>";

                                }
                        html += "<tr><td/><td/><td class='main'><a onclick='addsub(" + file + ");'> Add substitution string...</a></td><td/><td/></tr></tbody></table>";
                        document.getElementById("contents").innerHTML = html;
                    } catch(e)
                    {
                        if(xmlhttp.responseText != "null") //if not empty object
                            alert(xmlhttp.responseText); // error
                        document.getElementById("contents").innerHTML = "";
                    }
                }
            }
            xmlhttp.open('GET', 'builder.php?action=getcontents&file=' + file, true);
            xmlhttp.send();
        }

        function select(element)
        {
            files = element.parentNode.parentNode.parentNode.childNodes; //gets files
            for(var i = 0; i < files.length; i++)
                files[i].className = ""; //deselects all
            element.parentNode.parentNode.className += " selected"; //selects this file

        }

        function formatteddatetime(d)
        {
            if(d.getFullYear() == 1970) return "never";
            return td(d.getHours()) + ":" + td(d.getMinutes()) + " " + td(d.getDay()) + "-" + td(d.getMonth()) + "-" + d.getFullYear();
        }
        function td(n) //two digits        
        {
            return ((n > 9) ? n : "0" + n);
        }
        String.prototype.escapeHTML = function()
        {
            var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return this.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    </script>
</head>
<body>
    <h1><span>Builder v5</span> by ScanzySoftware <a onclick='build(this);'>Build now</a></h1>
    
    <h2>Files:</h2>
    <div id="files"></div>
    <div id="contents"></div>

    <div id="push"></div>
    <h2 id="footer">Copyright ScanzySoftware &copy; 2015</h2>

</body>
</html>