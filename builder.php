<?php

//copyright ScanzySoftware 2013-2014

//questo file costruisce il sito usando le informazioni nel file map.xml

define('mapfile','map.xml');

define('home','..');
define('mydir','../builder');
define('datadir','../builder/data');
define('layoutdir','../builder/layouts');
define('resdir','../builder/res');

$GLOBALS['errors']=0;

//gestisce errori di accesso al file mapfile
if (!file_exists(mapfile)) die("<strong>".red("FATAL ERROR:").grey(mapfile)." not found</strong>");
if (!is_readable(mapfile)) die("<strong>".red("FATAL ERROR:").grey(mapfile)." not readable</strong>");

ini_set("memory_limit","80M"); // alloca 80 megabyte per i dati
ini_set("max_input_time", 100);
ini_set("max_execution_time", 100); //evita che l'esecuzione non sia compromessa dal timer del server

//carica il file
info("Loading xml map file ".grey(mapfile));
$xml = simplexml_load_string(file_get_contents(mapfile));
info('Done',TRUE); 
echo '<br />';

if($xml==FALSE) die("<strong>".red("FATAL ERROR:").grey(mapfile)." not well-formed</strong>");

//inizia ad analizzare la cartella radice
processfolder($xml,'');

echo '<br /><br />';
for ($i=0;$i<50;$i++) echo '*';

//lo script e' terminato 
if ($GLOBALS['errors']==0){ //se non ci sono stati errori
  echo "<br /><br /><strong>SCRIPT TERMINATED ".blue("WITHOUT ERRORS")."</strong>";
} else { //se ci sono stati errori
  echo "<br /><br /><strong>SCRIPT TERMINATED ".red("WITH ".$GLOBALS['errors']." ERROR(S)")."</strong>";
}
flush();
//end of script

//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

//analizza la cartella specificata in path e rappresentata da $folder
function processfolder($folder,$path) {
  foreach($folder->page as $element) { //per tutti i file
    processfile($element,$path);  //analizza i file
  }
  
  foreach($folder->folder as $element) {  //per tutte le cartelle
  deleteifexists(home.$path.'/'.$element['name']); //cancella la cartella se esiste e se ci riesce
    if (mkdir(home.$path.'/'.$element['name'])) {  //crea la cartella
      success("created successfully dir ".grey(home.$path.'/'.$element['name']),TRUE); // se � stata creata correttamente
      processfolder($element,$path.'/'.$element['name']);   // analizza la cartella
    } else { // se c'� stato un errore
      error("can't create dir ".grey(home.$path.'/'.$element['name']),TRUE);
    }      
  }
  
  foreach($folder->file as $element) {  //per tutti i file di risorsa
    copyfile($element,$path);  //copia i file di risorsa
  }
  
  foreach($folder->res as $element) { //per tutte le cartelle di risorsa
    copyfolder($element,$path);  //copia le cartelle di risorsa
  }
}

//analizza il file pagina specificato in $path e rappresentato da $element
function processfile($element,$path) {
  if (file_exists(datadir.'/'.$element['data'])==FALSE) {
    error("can't find file ".grey(datadir.'/'.$element['data']));
    return;
  }
  
  info("Loading file ".grey(datadir.'/'.$element['data']));
  $root = simplexml_load_string(file_get_contents(datadir.'/'.$element['data']));  //carica in memoria il file xml con i dati
  info("Done",TRUE);
  
  if($root==FALSE){
    error("XML file ".grey(datadir.'/'.$element['data'])." not well-formed");
    return;
  }
  
  $content=processlayout($root);  // elebora i contenuti
  
  if (file_put_contents(home.$path.'/'.$element['name'],$content)!=strlen($content)){ //se non riesce a salvare su file
    error("cannot copy all bytes of ".grey(home.$path.'/'.$element['name']));  //visualizza un errore
  } else { //altrimenti
    success("successfully saved ".grey(home.$path.'/'.$element['name']));
  }
  
}

//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

//ritorna il testo elaborato in base agli elementi field e layout, funzione ricorsiva
function processlayout($element) { //element è il nodo xml <layout> da elaborare
  echo "<div style='display:none;border:2px solid black;margin:5px;padding:5px;'>"; flush();
  info("Processing layout ".grey(layoutdir.'/'.$element['src']));
  
  if (!(file_exists(layoutdir.'/'.$element['src']))) {
	error("can' t find layout file ".grey(layoutdir.'/'.$element['src']));
	 echo "</div>";flush();	
	return "";  
  }
  
  info("Loading file ".grey(layoutdir.'/'.$element['src']));
  $content=file_get_contents(layoutdir.'/'.$element['src']); //carica il file layout nella variabile
  info('Done',TRUE);
  
  foreach($element->field as $field){  // per ogni field
    echo "<div style='display:none;border:2px solid black;margin:5px;padding:5px;'>"; flush();
    info("Processing field ".$field['name']);
      
    if(count($field->layout) == 0 ){ //se il field non ha altri layout all'interno (e quindi solo testo)
      $replace= xml_contents($field); //copia il testo
    } else { // se invece ha altri layout all'interno
      info("field ".$field['name']." contains layouts",TRUE);
      $replace="";
      foreach($field->layout as $layout){ //ogni layout
        $replace=$replace.processlayout($layout); //viene elaborato ricorsivamente
      }
    }   
    $content=str_ireplace($field['name'],$replace,$content); // sostituisce i pezzi con es @title@  
    info("replaced text in field ".$field['name']." ".lightblue(htmlspecialchars($replace)),TRUE);
    echo "</div>";flush(); 
  }
  echo "</div>";flush(); 
  return $content;  // ritorna il contenuto elaborato
}

//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

//copia il file di risorsa specificato in $path e rappresentato da $element
function copyfile($element,$path){ //chiamata solo con $element <resfile>
  if (copy(resdir.'/'.$element['src'],home.$path.'/'.$element['name'])) { //copia il file
    success("copied successfully resource file ".grey(resdir.'/'.$element['src'])." to ".grey(home.$path.'/'.$element['name']));   
  } else {    // se la copia non riesce
    error("can't copy resource file ".grey(resdir.'/'.$element['src'])." to dir ".grey(home.$path));
  }
}

//copia la cartella di risorsa specificata in $path e rappresentata da $element
function copyfolder($element,$path){//chiamata solo con $element <resfolder>
  copydir(resdir.'/'.$element['src'],home.$path.'/'.$element['name']);
}

//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

//source by felix kling at stackoverflow.com
//comments by scanzy
function copydir($src,$dst) { //copia in modo ricorsivo i file e le cartelle da $src a $dst  
  $dir = opendir($src); // apre lo stream della cartella radice (suppongo)
  deleteifexists($dst); //cancella la cartella se esiste e se ci riesce 
  if (mkdir($dst)) { //crea la nuova cartella
    success("created successfully resource dir ".grey($dst),TRUE);  
    while(false !== ($file=readdir($dir))) { //finch� ci sono file nella cartella
      if (($file!='.')&&($file!='..')) { // e se sono realmente file 
        if (is_dir($src.'/'.$file)) { // se sono directory
          copydir($src.'/'.$file,$dst.'/'.$file); //vengono copiate ricorsivamente
        } else { //se sono file
          if (copy($src .'/'.$file,$dst.'/'.$file)){ //vengono copiati normalmente
            success("copied successfully resource file ".grey($src .'/'.$file)." to ".grey($dst.'/'.$file));  
          } else { // se la copia non riesce
            error("can't copy resource file ".grey($src .'/'.$file)." to ".grey($dst.'/'.$file));
          } 
        } 
      } 
    }
  } else { // se non si riesce la creazione della nuova cartella
    error("can't create resource dir ".grey($dst),TRUE);
  } 
  closedir($dir); //chiude lo stream della cartella radice (suppongo)   
}

//source by nbari at dalmp.com at php.net
//comments by scanzy
function deltree($dir) {  //elimina una cartella in modo ricorsivo
  $files = array_diff(scandir($dir), array('.','..')); // la lista dei file e cartelle in un array 
  foreach ($files as $file) {  // per ogni file e cartella
    (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file"); // cancella 
  } 
  return rmdir($dir); // prova a cancellare la cartella (riesce nell'operazione solo se � vuota)
} 
  
//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

//elimina la cartella se esiste e se ci riesce
function deleteifexists($folder){
  if (is_dir($folder)) {
    notice("folder ".grey($folder)." already exists. Attempting to delete its content...",TRUE);
    if (deltree($folder)){ // se non ci riesce dà errore, ma lo fara' anche il comando mkdir che non potr� sovrascrivere
      success("folder ".grey($folder)." and its content successfully deleted");
    } else {
      error("can't delete folder ".grey($folder)." and its content");
    }
  }
}

//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

//function by Ruud va at stackoverflow.com edited by scanzy to support attributes inside parent tag
//dal nodo $node ricava il testo all'interno, compresi i nodi figli
function xml_contents($node){
    $str = $node->asXML();
    return substr(substr($str,strpos($str,">")+1), 0, -3 - strlen($node->getName()));
} 

//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

//invia al browser delle informazioni specifiche
function info($msg,$isLast=FALSE){ //isLast e' usato per terminare la riga
  echo $msg.'&emsp;'; if ($isLast) echo "<br />"; flush();
}

//invia al browser un messaggio generico
function message($msg,$isImportant=FALSE){ //is important e' usato per le cartelle in modo tale da mettere un <br /> aggiuntivo per enfatizzare la riga
  echo lightblue("MESSAGE: ").$msg."<br />"; flush();  
  if ($isImportant) echo "<br />";
}

//invia al browser un messaggio di successo
function success($msg,$isImportant=FALSE){ //is important e' usato per le cartelle in modo tale da mettere un <br /> aggiuntivo per enfatizzare la riga
  echo blue("SUCCESS: ").$msg."<br />"; flush();  
  if ($isImportant) echo "<br />";
}

//invia al browser un messaggio di avviso
function notice($msg,$isImportant=FALSE){ //is important e' usato per le cartelle in modo tale da mettere un <br /> aggiuntivo per enfatizzare la riga
  echo purple("NOTICE: ").$msg."<br />"; flush();  
  if ($isImportant) echo "<br />";  
}

// invia al browser un messaggio di errore 
function error($msg,$isImportant=FALSE){ //is important e' usato per le cartelle in modo tale da mettere un <br /> aggiuntivo per enfatizzare la riga
  $GLOBALS['errors']++; //il numero di errori aumenta di uno
  echo red("ERROR: ").$msg."<br />"; flush();  
  if ($isImportant) echo "<br />";
}

//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

function red($txt) { // inserisce uno span color=red prima 
	return "<span style='color:#FF0000'>".$txt."</span>";
}

function blue($txt) { // inserisce uno span color=grey prima 
	return "<span style='color:#0000FF'>".$txt."</span>";
}

function lightblue($txt) { // inserisce uno span color=grey prima 
	return "<span style='color:#00CCCC'>".$txt."</span>";
}

function purple($txt) { // inserisce uno span color=grey prima 
	return "<span style='color:#AA00AA'>".$txt."</span>";
}

function grey($txt) { // inserisce uno span color=grey prima 
	return "<span style='color:#888888'>".$txt."</span>";
}

?>
