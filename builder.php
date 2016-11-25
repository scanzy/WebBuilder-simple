<?php

//builder v4 by ScanzySoftware
//25/10/2014 progettazione
//29/10/2014 30min di codice
//30/10/2014 1h di codice
//07/11/2014 1h di codice

/*
basic behaviour:

searches map.txt
foreach row in map.txt
source = before >
destination = after >
ignore after # (comment)

compare last build time and last modify

load source
load layout (layout: /path/to/file...)

foreach substitution pattern
search string (first)
substitute long string or layout

create dest dir if needed
save to destination

append log to builder.log

*/

//asks for password if needed
login();

//constants
define("MAPFILE","map.txt");
//define(LOGFILE,"builder.log");
define("FILESEPARATOR",">");
define("COMMENTCHAR","#");
define("ASSIGNCHAR",":");

//loads map file
$map = fileopentoread(MAPFILE,TRUE);

/*
//searches for logfile
if (!file_exists(LOGFILE))
{ //creates it if it doesn't exist
    $log = @fopen(LOGFILE,"w+");
    if ($log===FALSE)
    {
        error("Can't create ".LOGFILE,TRUE);
    }
    fclose($log);
}
else
{ //reads file
    $log = @fopen(LOGFILE,"r+");
    if ($log===FALSE)
    {
        error("Can't read from ".LOGFILE,TRUE);
    }
    while(feof($log))
    {
        $line = fgets($log); //reads from log
        
        $lastbuild[$file]=$time; 
    }
    fclose($log);
}
 */

$mapline = 0; //mapfile line count
$processedfiles = 0; //files processed

//foreach row in map.txt
while(!feof($map))
{
    $mapline++; //nextline
    $line = fgets($map); //gets line

    if (trim($line)=="") continue; //skips blank lines
    if (substr(trim($line),0,1)==COMMENTCHAR) continue; //skips comment lines

    $sd = explode(FILESEPARATOR,$line); //get source and destination
    if(!isset($sd[1])) 
    {
        error("not found char ".FILESEPARATOR." in ".MAPFILE." line ".$mapline);
        continue;
    }

    $source = trim($sd[0]); //gets trimmed source
    $dest = trim($sd[1]); //gets trimmed destination

    echo "s:".$source."  d:".$dest."<br/>";

    if(file_exists($dest)) //if never built before
    { //compare last build time and last modify       
        clearstatcache(); //clears cache
        if (filemtime($source) < filemtime($dest)) continue; //skips file if not modified since last build
    }

    //builds the source and outputs to the destination path

    $s = fileopentoread($source); //opens source
    if ($s===FALSE) continue; //skips file if failed to open

    $sline = 0; //source file lines count
    $phase = 0; //file read cycle phase
    $errorinfile = FALSE; //flag to exit file read line loop

    //process source file
    while(!feof($s))
    {
        $sline++;
        $line = fgets($s); //gets line;

        switch($phase) //switches by phase
        {
            case 0: //start of file (expected "layout:...")

                if (trim($line)=="") break; //skips blank lines
                if (isfirstword($line,COMMENTCHAR)) break; //skips comment lines

                if (!isfirstword($line,"layout")) //searches 'layout'
                {
                    error("expected 'layout' at line ".$sline." in source file".$source);
                    $errorinfile = TRUE; break;
                }
                $line = strafterword($line,"layout"); //gets only text after layout

                if (!isfirstword($line,ASSIGNCHAR)) //searches ASSIGNCHAR
                {
                    error("expected '".ASSIGNCHAR."' after 'layout' at line ".$sline." in source file".$source);
                    $errorinfile = TRUE; break;
                }
                $line = strafterword($line,ASSIGNCHAR); //gets only text after ASSIGNCHAR

                $output = filegetcontents(trim($line)); //loads layout
                if ($output===FALSE) //if fails
                {
                    $errorinfile = TRUE; break;
                }
                $phase = 1; //searches for end strings
            break;

            case 1: //searches for end strings (expected 'end:...')

                if (trim($line)=="") break; //skips blank lines
                if (isfirstword($line,COMMENTCHAR)) break; //skips comment lines

                if (!isfirstword($line,"end")) //searches 'end'
                {
                    error("expected 'end' at line ".$sline." in source file ".$source);
                    $errorinfile = TRUE; break;
                }
                $line = strafterword($line,"end"); //gets only text after end

                if (!isfirstword($line,ASSIGNCHAR)) //searches ASSIGNCHAR
                {
                    error("expected '".ASSIGNCHAR."' after 'end' at line ".$sline." in source file ".$source);
                    $errorinfile = TRUE; break;
                }
                $line = strafterword($line,ASSIGNCHAR); //gets only text after ASSIGNCHAR

                $end = trim($line); //gets end string

                if ($end=="") //if empty string
                {
                    error("expected string after 'end:' at line ".$line." in source file ".$source);
                    $errorinfile = TRUE; break;
                }
                else //if there's string
                {
                    $phase = 2; //searches for strings to search
                }
            break;

            case 2: //searches for strings to find (expected "search:...")

                if (trim($line)=="") break; //skips blank lines
                if (isfirstword($line,COMMENTCHAR)) break; //skips comment lines

                if (!isfirstword($line,"search")) //searches 'search'
                {
                    error("expected 'search' at line ".$sline." in source file ".$source);
                    $errorinfile = TRUE; break;
                }
                $line = strafterword($line,"search"); //gets only text after search

                if (!isfirstword($line,ASSIGNCHAR)) //searches ASSIGNCHAR
                {
                    error("expected '".ASSIGNCHAR."' after 'search' at line ".$sline." in source file ".$source);
                    $errorinfile = TRUE; break;
                }
                $line = strafterword($line,ASSIGNCHAR); //gets only text after ASSIGNCHAR

                $search = trim($line); //gets string to search

                if ($search=="") //if empty string
                {
                    error("expected string after 'search:' at line ".$line." in source file ".$source);
                    $errorinfile = TRUE; break;
                }
                else //if there's string
                {
                    $phase = 3; //searches for strings to substitute or layouts to substitute
                }
            break;

            case 3;  //searches for strings to substitute or layouts to substitute (expected 'layout:...' or 'sub:...')
                
                if (trim($line)=="") break; //skips blank lines
                if (isfirstword($line,COMMENTCHAR)) break; //skips comment lines

                if (isfirstword($line,"layout")) //searches 'layout'
                {
                    $line = strafterword($line,"layout"); //gets only text after layout

                    if (!isfirstword($line,ASSIGNCHAR)) //searches ASSIGNCHAR
                    {
                        error("expected '".ASSIGNCHAR."' after 'layout' at line ".$sline." in source file ".$source);
                        $errorinfile = TRUE; break;
                    }
                    $line = strafterword($line,ASSIGNCHAR); //gets only text after ASSIGNCHAR

                    $sub = filegetcontents(trim($line)); //loads layout
                    if ($sub===FALSE) //if faiis
                    {
                        $errorinfile = TRUE; break;
                    }
                    $phase = 2; //searches for strings to search

                }
                else if (isfirstword($line,"sub")) //searches 'sub'
                {
                    $line = strafterword($line,"sub"); //gets only text after sub

                    if (!isfirstword($line,ASSIGNCHAR)) //searches ASSIGNCHAR
                    {
                        error("expected '".ASSIGNCHAR."' after 'sub' at line ".$sline." in source file ".$source);
                        $errorinfile = TRUE; break;
                    }
                    $line = strafterword($line,ASSIGNCHAR); //gets only text after ASSIGNCHAR

                    if(trim($line)!="") //if is one line string
                    {
                        $sub = trim($line); //sets string to substitute

                        //performs the substitution
                        $replaced=0;
                        $output = str_replace($search,$sub,$output,$replaced);

                        if($replaced == 0) //if string to search not found
                        {
                            error("not found '".$search."' while processing source file ".$source);
                            $errorinfile = TRUE; break;
                        }
                        $phase = 2; //searches for strings to search

                    }
                    else //if is multiline string
                    {
                        $phase = 4; //copies everything to the string to substitute and searches the end string
                        $sub = ""; //initializes long string
                    }

                }
                else //if layout nor sub found
                {
                    error("expected 'layout' or 'sub' at line ".$sline." in source file ".$source);
                    $errorinfile = TRUE; break;
                }                                
            break;

            case 4: //copies everything to the string to substitute and searches the end string

                if(trim($line)!=trim($end)) //if not reached end of string
                {
                    $sub .= $line.CR; // appens line to string to substitute
                }
                else //if reached end of string
                {
                    //performs the substitution
                    $replaced=0;
                    $output = str_replace($search,$sub,$output,$replaced);

                    if($replaced == 0) //if string to search not found
                    {
                        error("not found '".$search."' while processing source file ".$source);
                        $errorinfile = TRUE; break;
                    }
                    $phase = 2; //searches for strings to search
                }
 
            break;
        }
        if ($errorinfile) break; //stops processing the file if there's some error
    }
    fclose($s);

    switch($phase) //errors if something was not terminated
    {
        case 0;
            error("not found main layout in file ".$file);
            $errorinfile = TRUE;
        break;
        
        case 1;
            error("not found 'end:' in file ".$file);
            $errorinfile = TRUE;
        break; 
        
        case 3;
            error("not found 'sub' or 'layout' after last 'search:' in file ".$file);
            $errorinfile = TRUE;
        break;   

        case 4;
            error("not found endstring '".$end."' after last 'sub:' in file ".$file);
            $errorinfile = TRUE;
        break;

        //case 2 is ok
    }

    if (!$errorinfile) //only if there weren't errors while processing
    {
        //creates dest directory if necessary
        if(!is_dir(dirname($dest)))
        {
            if(!@mkdir(dirname($dest), 0700, TRUE)) 
            { //if fails
                error("Can't create directory for destination file ".$dest);
                continue;
            }
        }

        //outputs the result to the destination file
        if (@file_put_contents($dest,$output)===FALSE)
        {
            error("failed to write to ".$dest);
            continue;
        }
        $processedfiles++;
    }
}

fclose($map);
echo "<br/><br/><b>builder teminated</b>, ".$processedfiles." file(s) processed correctly";
exit;

//-----------------------------------------------------------

function isfirstword($line,$word)
{
    return (substr(trim($line),0,strlen($word))==$word);
}

function strafterword($line,$word)
{
    return substr(trim($line),strlen($word));
}

//-----------------------------------------------------------

//displays error (if fatal error exits)
function error($err,$fatal=FALSE)
{
    if ($fatal)
    {
        echo "FATAL ERROR: ".$err;
        exit;
    }
    else
    {
        echo "ERROR: ".$err."<br/>";
    }
}

//opens a file for reading returning the handle or false if fails
function fileopentoread($file,$fatal=TRUE)
{
    //checks if exists
    if (!file_exists($file))
    {
        error("File not found ".$file,$fatal);
        return FALSE;
    }
    $handle = @fopen($file,"r"); //tries to open file

    if($handle===FALSE) //if error occurs
    {
        error("Can't read from file ".$file,$fatal);
    }
    return $handle;
}

//gets contents of a file returning handling errors
function filegetcontents($file,$fatal=TRUE)
{
    //checks if exists
    if (!file_exists($file))
    {
        error("File not found ".$file,$fatal);
        return FALSE;
    }
    $content = @file_get_contents($file); //get contents

    if($content === FALSE)  //if error occurs
    {
        error("Can't read from file ".$file,$fatal)
    }
    return $content;
}

function login()
{ echo "no login found!<br/>";}

?>