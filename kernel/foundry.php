<?php
/*
 * -File        foundry.php
 * -License     LGPL (http://www.gnu.org/copyleft/lesser.html)
 * -Copyright   2004, Nexista
 * -Author      joshua savage
 */

/**
 * @package     Nexista
 * @author      Joshua Savage
 */
 
/**
 * Load required files
 */
require_once('error.php');
require_once('debug.php');
require_once('config.php');
require_once('builder.php');
require_once('pathbuilder.php');

define('NX_BUILDER_LINEBREAK', "\n");

Nexista_Error::addObserver('display', 'Nexista_builderError');
function Nexista_builderError($e)
{
   $e->toHtml();  
}


/**
 * This class is reponsible for building a site/application
 * based on desired sitemap and configuration settings.
 *
 * The build process creates a compiled php file from the sitemap 
 * definition as well as the necessary file to handle the logic of 
 * presenting these files based on request.
 *
 * A typical build would output:
 * - A loader file such as index.php which is used as the 'entrance' file for the application.
 * - A switchbox file which is responsible for returning the proper data based on request
 * - A number of 'gate' php files, one for each section or 'page' of the sitema which  
 * are responsible for handling the per request logic and will load the 
 * necessary module files such as query definitions, php scripts, xsl stylesheets, 
 * - A configuration file based on our preferences.
 * 
 * To build an application, you will need a script to call the Foundry process.
 * Here is some sample code:
 * <code>
 * <?php
 * //load the application builder class
 * require_once('/home/lotus/nexista/kernel/foundry.php');
 * 
 * //instanciate and initialize it with our desired registry file
 * $foundry = Foundry::singleton();
 *
 * //load the master config, the user override config and process for 'live' mode 
 * $foundry->configure('./master.xml','./user.xml', 'live');
 * 
 * //build loader (i.e. index.php)
 * $foundry->buildLoader();
 * //compile the application 
 * $foundry->buildGates();  
 * $foundry->buildSitemap();
 * ?></code>
 *
 * @tutorial    reference.pkg
 * @package     Nexista
 */
class Nexista_Foundry
{

     /**
      * Hold an instance of the class
      *
      * @var    object
      */
     
    static private $instance;
    
    
    /**
     * Sitemap root node object
     *
     * @var      object
     */
   
     public $sitemapDocument;

    
    /**
     * Array to keep track of what tags are handled by what builder
     *
     * @var     array
     */

    private $builderTags = array();

    
    /**
     * Holds the gate info used to compile the cached sitemap file
     *
     * @var     array
     */

    private $sitemapData;
    
    /**
     * Print debug info when building
     *
     * @var     boolean
     */

    public $debug = false;
    
    
    /**
     * Read and writes the application config data
     *
     * This method loads the config data that holds the applciation
     * parameters such as paths, location of sitemap, 
     * session preferences, db connections, etc...
     * Some of this data will be written directly in the gate files
     * during the compile process. It takes the config xml and ouputs
     * it as combnined xml file for runtime.
     *
     * @param   string      master config file
     * @param   string      optional local override configuration file
     * @param   string      optional environment profile
     */

    public function configure($master, $local = null, $mode = null, $config_filename = 'config.xml')
    {
       
        $config = Nexista_Config::singleton();
        $config->setMaster($master);      
        $config->setLocal($local);
        $config->setMode($mode);
        $config->load();
        $config->writeConfig($config,$config_filename);
        
        
        //init some paths we may need for build
        $path = Nexista_Config::getSection('path');
        if(!defined('NX_PATH_APPS')) { 
            define("NX_PATH_APPS", $path['applications']);     
        }
        //init debug
        $configs = Nexista_Config::getSection('runtime');

        if(isset($configs['debug']) && ($configs['debug'] == 'on'))
        {
            $GLOBALS['debugTrack'] = true;
        }
	}
 
    
    
    /**
     * Returns the path to the sitemap
     * Useful for checking the mod time for required rebuilds
     * 
     * @return string   path to sitemap
     */
     
     public function getSitemapPath() { 
         
         return Nexista_Config::get('./build/sitemap');
         
     }    
    
    
    /**
     * Returns the path to the compile directory
     * 
     * @return string   path to sitemap
     */
     
     public function getCompilePath() { 
         
         return Nexista_Config::get('./path/compile');
         
     }
    
    /**
     * Builds the loader file (i.e. index.php)
     *
     * This method creates a loader file based on config settings.
     * This file is used as the 'entrance' file for the site, loading the
     * sitemap and appropriate gate file.
     *
     * @return  boolean     success of writing loader file
     */

    public function buildLoader()
    {
       
        
        $code[] = '<?php';
        $code[] = '/*';
        $code[] = ' * Domain Name:   '.$_SERVER['SERVER_NAME'];      
        $code[] = ' * Build Time:    '.date("D M j G:i:s T Y"); 
		$code[] = ' * Copyright:     ';
        $code[] = ' */';
		
		// The modes actually isn't necessary, because I build a site for
        // each server name that makes a request.
        /* 
        // this works, but better to leave mode setting up to the webserver
        $modes = Nexista_Config::getSection('modes');
		foreach($modes as $key => $value) { 

			Config::setMode($key);
			$path = Nexista_Config::getSection('path');
			$mode_domain = Nexista_Config::get('./domain/server_name');
		    $mode_vectors = Nexista_Config::get('./vectors/vector');
			$code[] = '//echo " vectors '.$mode_vectors.'";';	
	    //$client_method = Nexista_Config::get('./client/method');
	    if(strpos($mode_vectors,",")) { 
	    $mode_vectors = explode(",",$mode_vectors);
	    } else { 
	    $mode_vectors= array($mode_vectors);
	    }
	    foreach($mode_vectors as $nokey => $vector) { 
	    $vector_value = Nexista_Config::get("./client/$vector");
	    $code[] = 'if($_SERVER["'.$vector.'"]=="'.$vector_value.'") { ';  
	    }
        */
        //$key = $_ENV['NEXISTA_MODE'];
        $modes = Nexista_Config::getSection('modes');
		foreach($modes as $key => $value) { 
        Nexista_Config::setMode($key);
        $path = Nexista_Config::getSection('path');
        $code[] = 'if(!isset($_ENV["NEXISTA_MODE"])) { $_ENV["NEXISTA_MODE"]="'.$key.'"; }';
	    $code[] = 'if($_ENV["NEXISTA_MODE"]=="'.$key.'") { '; 
            $code[] = 'define("NX_PATH_BASE", "'.$path['base'].'");';  
            if(!defined('NX_PATH_CORE')) {
			$code[] = 'define("NX_PATH_CORE", "'.$path['base'].'kernel'.DIRECTORY_SEPARATOR.'");';   
            }
			$code[] = 'define("NX_PATH_LIB", "'.$path['base'].'lib'.DIRECTORY_SEPARATOR.'");';  
			
			$code[] = 'define("NX_PATH_HANDLERS", "'.$path['base'].'modules'.DIRECTORY_SEPARATOR.'handlers'.DIRECTORY_SEPARATOR.'");';  
			$code[] = 'define("NX_PATH_ACTIONS", "'.$path['base'].'modules'.DIRECTORY_SEPARATOR.'actions'.DIRECTORY_SEPARATOR.'");';
			$code[] = 'define("NX_PATH_VALIDATORS", "'.$path['base'].'modules'.DIRECTORY_SEPARATOR.'validators'.DIRECTORY_SEPARATOR.'");';
            
            
			$code[] = 'define("NX_PATH_COMPILE", "'.$path['compile'].'");';        
			$code[] = 'define("NX_PATH_CACHE", "'.$path['cache'].'");';  
            if(isset($path['logs'])) { 
			$code[] = 'define("NX_PATH_LOGS", "'.$path['logs'].'");';  
            }
			$code[] = 'define("NX_PATH_TMP", "'.$path['tmp'].'");';  
            
            
			$code[] = 'define("NX_PATH_PLUGINS", "'.$path['plugins'].'");';
            
            if(!defined('NX_PATH_APPS')) { 
			$code[] = 'define("NX_PATH_APPS", "'.$path['applications'].'");';
            }
			$code[] = 'require_once(NX_PATH_CORE."init.php");';
			$code[] = 'Nexista_Config::setMode("'.$key.'");';
			$code[] = 'define("NX_ID", "'.Nexista_Config::get('./build/query').'");';  
            
            
			$code[] = '$init = new Nexista_Init();';
			$prepend = Nexista_Config::get('./build/prepend');
			if(!is_null($prepend) AND file_exists($prepend)) 
				$code[] = '$init->loadPrepend("'.$prepend.'");';
			$code[] = '$init->start();';
			$code[] = '$init->display();';
			$code[] = '$init->stop();';
			/*
            foreach($mode_vectors as $key => $vector) {
			$code[] = '}';
			}
            */
			$code[] = '}';
			$code[] = '';					

		}

		

        $code[] = '?>';
		
		
		foreach($modes as $key => $value) { 
			Nexista_Config::setMode($key);
			return file_put_contents(Nexista_Config::get('./build/loader'), implode(NX_BUILDER_LINEBREAK,$code));
		}
        
    
    }
   
  
    /**
     * Loads the sitemap
     *
     */

    private function loadSitemap()
    {
        if(isset($_ENV['NEXISTA_MODE'])) { 
            Nexista_Config::setMode($_ENV['NEXISTA_MODE']);
        }
        //read sitemap as xml
        $this->sitemapDocument = new DOMDocument("1.0");
        $my_sitemap = Nexista_Config::get('./build/sitemap');
        $this->sitemapDocument->load($my_sitemap);
        
        
        //process includes
        $x = new DOMXPath($this->sitemapDocument);
        $res = $x->query('//map:include');
    
        if($res->length)
        {
            foreach($res as $include)
            {                
                $doc = new DOMDocument();
                $doc->load($include->getAttribute('src'));
                $new = $this->sitemapDocument->importNode($doc->documentElement,1);
                $include->parentNode->replaceChild($new,$include);
            }
        }
    }
  
    
    /**
     * Parses the sitemap, calling build process for each gate 
     *
     */
         
    public function buildGates()
    {
        //load sitemap
        $this->loadSitemap();
        
        //load builder classes
        $builderPath = Nexista_Config::get('./path/base')."modules".DIRECTORY_SEPARATOR."builders".DIRECTORY_SEPARATOR;
        $files = scandir($builderPath);
        //make instances for each for later gate building
        foreach($files as $file)
        {      
            //the # is for CVS backups which get in the way
            if(strpos($file, '.builder.php') AND !strpos($file, '#') AND !strpos($file, '_'))
            {
                //echo $file;
                //load class
                require_once($builderPath.$file);
                
                //store class
                $tag = str_replace('.builder.php', '', $file);
                $class = 'Nexista_'.ucfirst($tag).'Builder';               
                $obj =& new $class;
                $this->builderTags[$tag] =& $obj;
            }
        }

        //go through each gate
        $x = new DOMXPath($this->sitemapDocument);
        
        // "gate" should be changed to "match" to be compatible with cocoon / popoon
        $gates = $x->query('//map:gate');
        $count = 0;
        
        foreach($gates as $gate)
        {   
            //build gate
            $this->writeGate($this->parseGate($gate), $count);

            //add gate info for sitemap
            $this->buildConditions($gate, 'gate-'.$count.'.php');
            $count++;
        }
        
    }
    
    
    /**
     * Compiles the final sitemap file
     *
     * @param   string      xml array path to the gate info
     * @param   string      filename of gate that will be written in the sitemap file
     */

    private function buildConditions(&$gate, $filename)
    {
        if(!$gate->hasAttribute('name'))
        {
            //TODO better check and error handling here
            Error::init('No name for gate', NX_ERROR_FATAL);
        }
       // "name" should be changed to "pattern" for compatibility with popoon / cocoon
        if(!$gate->hasAttribute('pattern'))
        {
            //TODO better check and error handling here
            //Error::init('No name for gate', NX_ERROR_FATAL);
        }
        
        //cache time?
        $cache = -1;
        if($gate->hasAttribute('cache'))
        {
            $cache = $gate->getAttribute('cache');
        }
        //client_cache?
        $client_cache = -1;
        if($gate->hasAttribute('client_cache'))
        {
            $client_cache = $gate->getAttribute('client_cache');
        }
        //role time?
        $role = -1;
        if($gate->hasAttribute('role'))
        {
            $role = $gate->getAttribute('role');
        }

        
        if(preg_match('~^regex:(.*)$~', $gate->getAttribute('name'), $m))
        {
            $match = 'regex';
            $name =  $m[1];
            
        }
        else
        {
            $match = 'exact';
            $name = $gate->getAttribute('name');
        }
        
        if($this->debug) { 
            echo "<a href='?nid=".$name."'>".$name."</a>...<br>\n";
		} 
        @$this->sitemap[$match][$name] = array('uri'=>$filename, 'role'=>$role, 'cache'=>$cache, 'client_cache'=>$client_cache);
       
    }


    /**
     * Compiles the final sitemap file
     *
     * This method takes $this->sitemap array which
     * contains all the gate info and builds an if/else
     * condition map out of it. This file will be used
     * as the sitemap for the site
     *
     */

    public function buildSitemap()
    {
   
        //build top of file (reqs, etc)
        $code[] = "<?php";
        $code[] = '//Build Time: '.date("D M j G:i:s T Y"); 
        $code[] = '$_ID_ = !empty($_GET["'.Nexista_Config::get('./build/query').'"]) ? $_GET["'.Nexista_Config::get('./build/query').'"] : "'.Nexista_Config::get('./build/default').'";';
        $code[] = "\$redirect = isset(\$_GET['redirect']) ? \$_GET['redirect'] : null;";
           
        foreach($this->sitemap as $type => $elements)
        {
            $code[] = '$gates'.ucfirst($type).' = array(';
            foreach($elements as $name => $info)
            {
                $this_gate = "'".$name."'=>array('uri'=>'".$info['uri']."'";
                // Server cache's need to include auth if there is a role specified.
                if($info['cache'] !== -1 && $info['role'] !== -1){
                    $this_gate .= ",'cache'=>".$info['cache'].",'role'=>'".$info['role']."'";   
                    
                } elseif($info['cache'] !== -1 && $info['role'] === -1){
                    $this_gate .= ",'cache'=>".$info['cache'];      
                
                } elseif($info['role'] !== -1 && $info['cache'] === -1 ){
                    $this_gate .= ",'role'=>'".$info['role']."'";     
                } 
                // Client cache's do not need check for auth.
                if($info['client_cache'] !== -1){
                    $this_gate .= ",'client_cache'=>'".$info['client_cache']."'";     
                } 
                $this_gate .= "),";
                $code[] = $this_gate;
            }
            $code[] = ');';
        }

        //setup 404 handling       
        $missing = Nexista_Config::get('./build/missing');
        if(!empty($missing) && isset($this->sitemap['exact'][$missing]))
        {   
            ob_start();
            $missing_gate = var_export($this->sitemap['exact'][$missing]);
            $missing_gate = ob_get_contents();
            ob_end_clean();
            $code[] = '$gateMissing = '.$missing_gate.';';
        }
        else
            $code[] = '$gateMissing = null;';
        
        //slap footer
        $code[] = '?>';

        $data = implode(NX_BUILDER_LINEBREAK,$code);
        
        //save file
        $tmp = fopen(Nexista_Config::get('./path/compile').'sitemap.php', "w+");
        if(flock($tmp, LOCK_EX))
        {
            fwrite($tmp, $data);
            flock($tmp, LOCK_UN);
        }
        fclose ($tmp);
    }
    
    
    /**
     * Build an individual gate file
     *
     * @param   object      reference to gate object     
     * @return  string      content of gate to write
     */
     
    private function parseGate(&$gate)
    {

        //init array for required file
        $required = array();
        
        $code = '';
    
        
        //foreach action
        $this->parseGateCallback($gate, $code, $required);
        
        //get prepend tags
        $res = $this->sitemapDocument->getElementsByTagName('prepend');
        $prependCode = '';
        if($res->length)
        {
            $prependGate = $res->item(0);
            $this->parseGateCallback($prependGate, $prependCode, $required);
        
        }
       
        //add header and required files
        $content = $this->addGateHeader($gate, $required);

        //add prepend and main code
        $content .= $prependCode.$code;

        //slap footer in
        $content .= $this->addGateFooter($gate);

        //add append code
        //ya...doesn't exist yet :)

        return $content;

    }

    
    /**
     * Callback file to build an individual gate file 
     *
     * @param   object      reference to current tag being processed
     * @param   string      reference to current code content for gate
     * @param   array       reference to required include files for this tag     
     * @return  boolean     success
     */
     
    private function &parseGateCallback(&$tag, &$code, &$required)
    {
        foreach( $tag->childNodes as $action)
        {
            if(get_class($action) != 'DOMElement')
                continue;
 
            $module = str_replace('map:', '', $action->nodeName);

            if(in_array($module, array_keys($this->builderTags)))
            {
                $obj =& $this->builderTags[$module];
      
                //add required files
                $required = array_merge($required, $obj->getRequired());
  
                //pass current object node if needed                 
                $obj->action =& $action;

                //add debug code?
                $non_debug_modules = array('if','true','false','case','switch');
                if(isset($GLOBALS['debugTrack']) && $GLOBALS['debugTrack'] === true && !in_array($module,$non_debug_modules))
                    $code .= $this->addGateDebugStart($module);
                   
                //get start of code
                $text = $obj->getCodeStart();
    
                if(!empty($text))
                    $code .= $text.NX_BUILDER_LINEBREAK;

                //get nested tag code
                if(!$this->parseGateCallback($action, $code, $required))
                    return false;

                //get end code
                $text = $obj->getCodeEnd();

                if(!empty($text))
                    $code .= $text.NX_BUILDER_LINEBREAK;

                //reset builder values such as attributes
                $obj->reset();

                //add debug code?
                if(isset($GLOBALS['debugTrack']) && $GLOBALS['debugTrack'] === true && !in_array($module,$non_debug_modules))
                    $code .= $this->addGateDebugStop($module);
                    
            }
            else
            {
                Error::init("No $module builder module found!", NX_ERROR_FATAL);
            }
        }        
        return true;
    }
      

    /**
     * Compiles individual gate files
     *
     *
     * @param   string      the cdata content for this gate
     * @param   string      the number (in order) of this gate
     */

    private function writeGate(&$gatedata, &$gatenum)
    {

        //write gate file
        $compile_path = Nexista_Config::get('./path/compile');
        // To do: test for folder, if not, try to create, if not, throw error.
        if(!is_dir($compile_path)) {
            `mkdir -p $compile_path`;
        }
        $gatefile = fopen($compile_path. 'gate-'.$gatenum.".php","w+");

        if (flock($gatefile, LOCK_EX))
        {
            fwrite($gatefile, $gatedata);

            flock($gatefile, LOCK_UN);

        }
        fclose($gatefile);
    }


    /**
     * Returns gate prepend code
     *
     * @return  string      prepend code
     */
    /*
    private function addPrepend()
    {
        $code[] = "Nexista_Debug::register('in','".$mod."');";
        return implode(NX_BUILDER_LINEBREAK,$code).NX_BUILDER_LINEBREAK;
    }
    */
    /**
     * Adds gate debug registration
     *
     * @return  string      prepend code
     */
     
    private function addGateDebugStart($mod)
    {
        $code[] = "Nexista_Debug::register('in','".$mod."');";
        return implode(NX_BUILDER_LINEBREAK,$code).NX_BUILDER_LINEBREAK;

    }


    /**
     * Returns gate prepend code
     *
     * @return  string      prepend code
     */
     
    private function addGateDebugStop($mod)
    {

        $code[] = "Nexista_Debug::register('out','".$mod."');";
        return implode(NX_BUILDER_LINEBREAK,$code).NX_BUILDER_LINEBREAK;

    }

    /**
     * Returns gate prepend code
     *
     * @return  string      prepend code
     */
     
    private function addGateFooter(&$obj)
    {      
        $code[] = '?>';
        return implode(NX_BUILDER_LINEBREAK,$code);
    }

    
    /**
     * Returns gate prepend code
     *
     * @return  string      prepend code
     */
     
    private function addGateHeader(&$obj, $req = array())
    {
        $code[] = '<?php';
        $code[] = '/*';
        $code[] = ' * Gate Name:     '.$obj->getAttribute('name');   
        $matchType = ($obj->getAttribute('match')== 'regex') ? 'regex':'normal';     
        $code[] = ' * Match Type:    '.$matchType;
        $code[] = ' * Build Time:    '.date("D M j G:i:s T Y"); 
        $code[] = ' */';
        //$code[] = 'define("NX_DEBUG_INFO_GATE", "'.$obj->getAttribute('name').'");';
        $req = array_unique($req);
        foreach($req as $r)
        {
            $code[] = "require_once('".$r."');";
        }

        $code[] = '$flow = Nexista_Flow::singleton();';
        $code[] = '$output  = null;';

        return implode(NX_BUILDER_LINEBREAK,$code).NX_BUILDER_LINEBREAK;
    }
 
    /**
     * Returns gate prepend code
     *
     * @return  string      prepend code
     */
     
    static public function singleton() 
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }

        return self::$instance;
    }
    
} //end class
?>