<?php
    

//builder.php v3 by ScanzySoftware 
//01/08/2014-05/08/2014

/*
basic behaviour:

list recursively files in array with path and size and type (bool is resource)
calculate total
foreach element of array
create directory if needed
switch is resource
process pages
or
copy file

process file:
load in string
process raw
copy file

copy file:
copy to file with buffer
update progress bar
show link

process raw:
search constants
search layout
if layout present process layout
replace layout

process layout:
search src
replace layout
search field
process field
replace field

process field:
search name
find name
process raw
replace raw
*/

session_start();

$password="qwerty";

$layoutrootfolder="layouts";
$pagerootfolder="data";
$resrootfolder="res";
$outputfolder="..";
$bufsize=2048;

define("STARTOFLAYOUT","<layout>");
define("ENDOFLAYOUT","</layout>");
define("STARTOFSRC","<src>");
define("ENDOFSRC","</src>");
define("STARTOFFIELD","<field>");
define("ENDOFFIELD","</field>");
define("STARTOFNAME","<name>");
define("ENDOFNAME","</name>");

define("LOGINFORM","<form style='text-align:center' action='' method='POST'><h1>Builder v3</h1><h3>by ScanzySoftware</h3><label>Password:</label><input type='password' name='password' /><input type='submit' value='Log In' /></form>");
define("LOGINEMPTY","<form style='text-align:center' action='' method='POST'><h1>Builder v3</h1><h3>by ScanzySoftware</h3><label>Password:</label><input type='password' name='password' /><input type='submit' value='Log In' /><p>Please insert password</p></form>");
define("LOGINWRONG","<form style='text-align:center' action='' method='POST'><h1>Builder v3</h1><h3>by ScanzySoftware</h3><label>Password:</label><input type='password' name='password' /><input type='submit' value='Log In' /><p style='color:red'>Wrong password!</p></form>");

//builds the site
function build()
{
    global $pagerootfolder, $resrootfolder;

    $_SESSION['building']=TRUE; //start of build

    resetvariables(); //session variables' setup

    //lists all files recursively (with size)
    $pages=listfiles($pagerootfolder);
    $resources=listfiles($resrootfolder);

    //counts files to process/copy
    $_SESSION['totpagecount']=count($pages);
    $_SESSION['totrescount']=count($resources);

    //calculates the total bytes to copy/process
    $_SESSION['totpagebytes']=totalbytes($pages);
    $_SESSION['totresbytes']=totalbytes($resources);
    
    //process and save pages
    foreach($pages as $file => $size) processfile($file);

    //copy res files
    foreach($resources as $file => $size) copyfile($file);

    $_SESSION['building']=FALSE; //finish of build
    exit(); //exits th script
}

//resets variables
function resetvariables()
{
    //bytes already processed
    $_SESSION['pagebytes']=0;
    $_SESSION['resbytes']=0;

    //total bytes
    $_SESSION['totpagebytes']=0;
    $_SESSION['totresbytes']=0;

    //files already processed
    $_SESSION['pagecount']=0;
    $_SESSION['rescount']=0;

    //total files
    $_SESSION['totpagecount']=0;
    $_SESSION['totrescount']=0;

    //errors
    $_SESSION['errors']=array();

    //clears filesystem cache
    clearstatcache();
}

//returns an array with all file names and sizes: array(filename => size)
function listfiles($rootdir)
{
    $filesizes = array();
    $files = scandir($rootdir); //lists files and dirs    
    foreach($files as $file)
    {
        if(is_file($rootdir."/".$file)) //if file
        {        
            $filesizes[$rootdir."/".$file] = filesize($rootdir."/".$file);  //stores in array
        }
        else if(is_dir($rootdir."/".$file) && $file!="." && $file!="..") //if dir
        {
            //$fileswithdir = array(); //list files in dir and fills another array with dirname
            foreach(listfiles($rootdir."/".$file) as $fileindir => $size) //$filewithdir[$rootdir."/".$file.'/'.$filenodir] = $size;
            $filesizes[$fileindir] = $size;
            //array_merge($filesizes,$filewithdir); //merges the arrays
        }
    }
    return $filesizes;
}

//returns the total size of files listed in $files
function totalbytes($files)
{
    $bytes = 0;
    foreach($files as $file => $size) $bytes += $size;
    return $bytes;
}

//processes and then saves the file $file updating in real time $_SESSION['pagebytes'], $_SESSION['pagecount'], $_SESSION['errors']
function processfile($source)
{
    global $outputfolder,$pagerootfolder;
    $dest = $outputfolder."/".substr($source,strlen($pagerootfolder)); //destination file

    if(!file_exists($source)) //if the file doesn't exists
    {
        error($source,"Source file not found"); //error
    }
    else if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, TRUE)) //if can't create dir if needed
    {
        error(dirname($dest),"Failed to create folder"); //error
    }  
    else if (!$s=@fopen($source,"rb")) //if the source file fails to open
    {
        error($source,"Failed to open source file"); //error
    }
    else if (!$d=@fopen($dest,"wb")) //if the destination file fails to open
    {
        error($dest,"Failed to create/open/overwrite destination file"); //error
        fclose($s); //closes the source file
    }
    else //if everything ok
    {
        processpage($s, $d, TRUE); //processes the file     
        fclose($s); //closes source file
        fclose($d); //closes destination file
    }
    
    $_SESSION['pagecount']++; //updates copied files count
}

//copies the file $file updating in real time $_SESSION['resbytes'], $_SESSION['rescount'] and $_SESSION['errors']
function copyfile($source)
{
    global $outputfolder, $resrootfolder, $bufsize;
    $dest = $outputfolder."/".substr($source,strlen($resrootfolder)); //destination file

    if(!file_exists($source)) //if the file doesn't exists
    {
        error($source,"Source file not found"); //error
    }
    else if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, TRUE)) //if can't create dir if needed
    {
        error(dirname($dest),"Failed to create folder"); //error
    }  
    else if (!$s=@fopen($source,"rb")) //if the source file fails to open
    {
        error($source,"Failed to open source file"); //error
    }
    else if (!$d=@fopen($dest,"wb")) //if the destination file fails to open
    {
        error($dest,"Failed to create/open/overwrite destination file"); //error
        fclose($s); //closes the source file
    }
    else //if everything ok
    {
        while (!feof($s)) //if end of file not reached
        {
            if (($buf = @fread($s, $bufsize)) === FALSE) //reads from source file
            {
                error($source,"Error while reading resource file");
                break;
            }
            if (($bytes = @fwrite($d, $buf)) === FALSE) //writes to destination file
            {
                error($dest,"Error while writing resource file");
                break;
            }  
            $_SESSION['resbytes']+=$bytes; //updates copied bytes count         
        }
        fclose($s); //closes source file
        fclose($d); //closes destination file
    }
    
    $_SESSION['rescount']++; //updates copied files count
}

//called on error $msg in file $file
function error($file,$msg)
{
    $_SESSION['errors'][]=array('file' => $file, 'msg' => $msg); //adds error info to the array
}

//processes the page (searches layouts)
function processpage(&$s,&$d)
{
    while (1) //foreach layout in page
    {
        //searches the start of layout
        if (!copyandsearch($s, $d, array(STARTOFLAYOUT), FALSE)) return;

        //processes the layout
        if (!processlayout($s,$d)) return;
    }
}

//processes the layout (reads its src first) Remark: call this function after STARTOFLAYOUT
function processlayout($s,$d)
{
    global $layoutrootfolder;

    //searches the start of layout's src
    if (!search($s,array(STARTOFSRC))) return FALSE;

    //searches the end of layout src
    if (($layout = readandsearch($s,ENDOFSRC)) === FALSE) return FALSE;
        
    //opens the layout
    if (!file_exists($layoutrootfolder."/".$layout)) //if it doesn't exist
    {
        error(stream_get_meta_data($s)['uri'],"Layout file ".$layoutrootfolder."/".$layout." not found"); //error
        return FALSE;
    }

    $_SESSION['totpagebytes'] += filesize($layoutrootfolder."/".$layout); //there are some more bytes to read

    if (!$l = @fopen($layoutrootfolder."/".$layout,"rb")) //if it falis to open
    {
        error($layout,"Failed to open layout file"); //error
        return FALSE;
    }            
        

    while (1) //for each field
    {
        //searches the end of layout or start of a field
        if (($found = search($s, array(STARTOFFIELD, ENDOFLAYOUT))) === FALSE) return FALSE;

        //if layout has been processed correctly
        if ($found == ENDOFLAYOUT) return TRUE;

        //searches the start of field name
        if (!search($s,array(STARTOFNAME))) return FALSE; 

        //reads field name
        if (($name = readandsearch($s,ENDOFNAME)) === FALSE) return FALSE;

        //copies some of the layout
        if (!copyandsearch($l, $d, array($name))) return FALSE;

        while (1) //until reached end of field
        {
            //copies the field content
            if (($found = copyandsearch($s, $d, array(ENDOFFIELD, STARTOFLAYOUT))) === FALSE) return FALSE;

            //if field has been processed correctly
            if ($found == ENDOFFIELD) break;
            
            //processes layout
            if (!processlayout($s,$d)) return FALSE;
        }
    }
}

//copies bytes until finds something in $search, then returns the found string, if reaches eof or errors occur, false 
function copyandsearch(&$s, &$d, $search, $erroroneof = TRUE)
{
    while (!feof($s)) //while there's something to read
    {
        $read=""; //text read

        //checks $search strings
        foreach($search as $str)
        {
            for ($found = 0; $found<strlen($str); $found++)
            {   
                if (($byte = readbytes($s)) === FALSE) return FALSE; //reads the byte
                
                $read .= $byte;
                the bug is here!
                if ($str[$found] != $byte) break; //controls the $byte

                if ($found>=strlen($str)-1) return $str; //$str found!
            }
        }

        //writes the byte(s)
        if (($bytes = writebytes($d,$byte)) === FALSE) return FALSE;
    }
    if ($erroroneof) error(stream_get_meta_data($s)['uri'],array_values($search)." not found");
    return FALSE;    
}

//reads bytes until finds $search, then returns the text read, if reaches eof or errors occur, false 
function readandsearch(&$s, $search)
{
    $str="";
    while (!feof($s)) //while there's something to read
    {        
        //checks $search word
        for ($found = 0; $found<strlen($search); $found++)
        {
            if (($byte = readbytes($s)) === FALSE) return FALSE; //reads the byte
                        
            if ($search[$found] != $byte) break; //controls the $byte

            if ($found>=strlen($search)-1) return $str; //$search string found!
        }
        $str .= $byte; //saves the byte(s)
    }
    return FALSE;    
}

//reads bytes until finds something in $search, then returns the found string, if reaches eof or errors occur, false 
function search(&$s, $search)
{
    while (!feof($s)) //while there's something to read
    {
        //checks $search strings
        foreach($search as $i => $str)
        {
            for ($found = 0; $found<strlen($str); $found++)
            {   
                if (($byte = readbytes($s)) === FALSE) return FALSE; //reads the byte
            
                if ($str[$found] != $byte) break; //controls the $byte

                if ($found>=strlen($str)-1) return $str; //$str string found!
            }
        }
    }
    return FALSE;    
}

//reads one byte from $s and returns it, if errors occur or reached eof, returns FALSE
function readbytes(&$s)
{
    if (($read = @fread($s, 1)) === FALSE) 
    {
        error(stream_get_meta_data($s)['uri'],"Error while reading page file");
        return FALSE;
    }
    $_SESSION['pagebytes']++; //updates read bytes count
    return $read;
}

//writes $byte to $d and returns the bytes copied, if errors occur or reached eof, returns FALSE
function writebytes(&$d,$byte)
{
    if (($bytes = @fwrite($d, $byte)) === FALSE) 
    {
        error(stream_get_meta_data($d)['uri'],"Error while writing page file");
        return FALSE;
    }
    return $bytes;
}

//executes the login
function login()
{
    global $password;

    //saves the password
    if(isset($_POST['password'])) $_SESSION['password'] = $_POST['password'];

    if(!isset($_SESSION['password'])) //if password not given
    {
        echo LOGINFORM; //asks for login
        exit();
    }
    else if ($_SESSION['password'] == "")
    {
        echo LOGINEMPTY; //asks for password
        exit();
    }
    else if ($_SESSION['password'] != $password) //if wrong password
    {
        echo LOGINWRONG; //displays notice
        exit; 
    }
}

//logs out
function logout()
{
    unset($_SESSION['password']);
    unset($_POST['password']);
}

//gives info on building process
function info()
{
    header("Content-type: application/xml");
    echo '<?xml version="1.0" encoding="utf-8"?>'.
    '<root>'.
    '<x id="s">'.($_SESSION['building']?'1':'0').'</x>'.
    '<x id="pt">'.round(($_SESSION['pagebytes'] + 0.1) / ($_SESSION['totpagebytes'] + 0.1) * 100, 0, PHP_ROUND_HALF_DOWN).'</x>'.
    '<x id="pc">'.$_SESSION['pagecount'].'/'.$_SESSION['totpagecount'].'</x>'.
    '<x id="rt">'.round(($_SESSION['resbytes'] + 0.1) / ($_SESSION['totresbytes'] + 0.1) * 100, 0, PHP_ROUND_HALF_DOWN).'</x>'.
    '<x id="rc">'.$_SESSION['rescount'].'/'.$_SESSION['totrescount'].'</x>'.
    '<x id="et">'.count($_SESSION['errors']).'</x>';
    foreach($_SESSION['errors'] as $error) echo '<error><file>'.$error['file'].'</file><msg>'.$error['msg'].'</msg></error>';
    //echo '<x id="alert">'.$_SESSION['pagebytes']."/".$_SESSION['totpagebytes'].'</x>';
    echo '</root>';
}


login();

if (isset($_REQUEST['action']))
{
    switch($_REQUEST['action'])
    {
        case "build":
        build();  
        break;  
            
        case "info":
        info();
        break;

        case "logout":
        logout();
        login();
    }
    exit();
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Builder.php v3 by ScanzySoftware</title>
        <style>
            body {
                background-color: #583a3a;
                text-align: center;
                color: white;
                margin: 0;
            }
            div {overflow: hidden;}
            #main {
                width: 950px; 
                margin: 20px auto;                
            }
            #header {
                text-align: center;
                background-color: #8b3d3d;
                margin: 0 0 10px;
            }
            #header h1 {
                margin: 20px;
                font-size: 85px;
            }
            #header h2 {
                margin: 20px;
                font-size: 20px;
            }
            .section {
                background-color: #a94848;
                margin: 0 0 10px;
            }
            #navbar {
                background-color: #a94848;
            }
            #navbar, #navbar ul {
                margin: 0;
                padding: 0;
            }
            #navbar li {
                display: inline-block;
                list-style: none;
                margin: 0;
                padding: 10px;
            }
            #navbar a {
                margin: 0;
                padding: 10px;
                text-decoration: none;
                font-size: 25px;
                color: white;
            }
            #navbar a:hover {
                background-color: #583a3a;
            }
            .total {
                background-color: #8b3d3d;
            }
            .progress {
                height: 20px;
                width: 0;
                background-color: #fc9393;              
            }
            .title {
                margin: 0;
                padding: 10px;
            }
            .subtitle {
                margin: 0;
                padding: 0 10px 10px;
            }
            #errors table {
                width: 950px;
                margin: 0;
            }
            #errors td {
                padding: 5px;
                background-color: #8b3d3d;
            }
            #footer {background-color: #a94848;}
            #footer p {margin:0; padding: 10px;font-size: 20px;}
        </style>
        <script>
            var building = false;
            var interval;

            function build()
            {
                if(!building)
                {
                    resetvalues();
                    building = true;
                    xmlhttp = new XMLHttpRequest();
                    xmlhttp.open("POST", "builder.php", true);
                    xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                    xmlhttp.send("action=build");
                    document.getElementById('bb').innerHTML = "Building...";
                    interval = setInterval(updatedata, 500);
                }
            }
            function updatedata()
            {
                xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function()
                {
                    if(xmlhttp.readyState == 4 && xmlhttp.status == 200)
                    {
                        root = xmlhttp.responseXML;

                        datafields = ['pt', 'pc', 'rt', 'rc', 'et'];
                        for(i = 0; i < datafields.length; i++) transferdata(root, datafields[i]);

                        updatebars();

                        errors = root.getElementsByTagName('error');
                        grid = document.getElementById('eg');
                        for(i = 0; i < errors.length; i++)
                        {
                            grid.innerHTML += "<tr><td>" + errors[i].getElementsByTagName('file')[0].innerHTML + "</td><td>" + errors[i].getElementsByTagName('msg')[0].innerHTML + "</td></tr>";
                        }

                        if ((al = root.getElementById('alert')) != null) alert(al.innerHTML);

                        building = (root.getElementById('s').innerHTML == '0') ? false : true;
                        if(!building)
                        {
                            clearInterval(interval);
                            document.getElementById('bb').innerHTML = "Build again";
                        }
                    }
                };
                xmlhttp.open("POST", "builder.php", true);
                xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                xmlhttp.send("action=info");
            }
            function transferdata(root, id) { document.getElementById(id).innerHTML = root.getElementById(id).innerHTML; }
            function updatebars()
            {
                document.getElementById('pp').style.width = document.getElementById('pt').innerHTML + '%';
                document.getElementById('rp').style.width = document.getElementById('rt').innerHTML + '%';
            }
            function resetvalues()
            {
                document.getElementById('pc').innerHTML = '0/0';
                document.getElementById('rc').innerHTML = '0/0';
                document.getElementById('et').innerHTML = '0';
                document.getElementById('pt').innerHTML = '0';
                document.getElementById('rt').innerHTML = '0';
                document.getElementById('eg').innerHTML = '';
                updatebars();
            }
        </script>
        <!--script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script-->
    </head>
    <body>
        <div id="main">
            <div id="header">
                <h1>Builder.php v3</h1>
                <h2>by ScanzySoftware</h2>
                <div id="navbar">
                    <ul>
                        <li><a id="bb" href="javascript:void(0)" onclick="build();return false;">Build</a></li>
                    </ul>
                </div>
            </div>
            <div id="content">
                <div id="pages" class="section">
                    <h1 class="title">Pages: <span id="pt">0</span>%</h1>
                    <h2 class="subtitle">processed pages: <span id="pc">0/0</span></h2>
                    <div class="total"><div class="progress" id="pp"></div></div> 
                </div>
                <div id="resources" class="section">
                    <h1 class="title">Resources: <span id="rt">0</span>%</h1>
                    <h2 class="subtitle">copied resources: <span id="rc">0/0</span></h2>
                    <div class="total"><div class="progress" id="rp"></div></div>
                </div>
                <div id="errors" class="section">
                    <h1 class="title">Errors</h1>
                    <h2 class="subtitle"><span id="et">0</span> errors</h2>
                    <table><tbody id="eg"></tbody></table>
                </div>
            </div>
            <div id="footer">
                <p>Copyright &copy; ScanzySoftware 2014</p>
            </div>
        </div>
    </body>
</html>