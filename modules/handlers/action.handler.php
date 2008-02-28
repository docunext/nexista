<?php
/*
 * -File        action.handler.php
 * -License     LGPL (http://www.gnu.org/copyleft/lesser.html)
 * -Copyright   2002-2007, Nexista
 * -Author      joshua savage
 */

/**
 * @package     Nexista
 * @subpackage  Handlers
 * @author      Joshua Savage 
 */
 
/**
 * This class handles data filtrattion/ modification such as
 * stripping html tags, removing new lines, etc...
 *
 * @package     Nexista
 * @subpackage  Handlers
 */

class Nexista_ActionHandler
{

    /**
     * Accepts an xml list of items and actions them according
     * to passed criteria
     *
     * This function will action a number of data fields as
     * described in a action xml file. Actions modify the data
     * in some way such as strip html, nl2br, etc..
     *
     *
     * @param   string      the name of the xml data file
     * @return  boolean     false if any field failed
     * @see
     */
    public function process($src)
    {
        //load descriptor file and parse
        $xml = simplexml_load_file($src);
       

        //parse through each node and process
        foreach ($xml->children() as $action)
        {
        self::processItem((string)$action['type'], (string)$action['params']);
        }

        return true;

    }


    /**
     * This function will apply one action to the source provided
     *
     * @param   string      tag name of flow value to change.
     * @param   string      the type of action
     */


    static public function processItem($type, $params)
    {

        //load the action module file based on $type
        require_once(NX_PATH_CORE . "action.php");

        //load the action module file based on $type
        require_once(NX_PATH_ACTIONS . trim(strtolower($type)) . ".action.php");

        //get the action parameters
        $params = explode(',',$params);

        if(!self::evaluateVars($params))
        {
            return false;
        }

        //build the class name to load
        $classname = 'Nexista_' . trim(ucfirst($type)) . "Action";

        $action = new $classname();

        if(!$action->process($params))
        {
            return false;
        }

        return true;
    }


    /**
     * Processes a string for variables to evaluate
     *
     * This function processes a string for ...:// substrings
     * which indicate variables it should evaluate.
     * Ex: flow://, globals://, etc..
     *
     * @param   array       array of parameters unique to the action
     */

   static private function evaluateVars(&$params)
    {

        foreach($params as $val)
        {
            //see if we have anything to evaluate
            if(strstr($val,'://') && !strstr($val,'http://'))
            {
                $val = Nexista_Flow::getByPath($val);
                if(is_null($val) or is_array($val))
                {
                    Nexista_Error::init("$val variable called in action params does not exist", NX_ERROR_WARNING);
                }

            }

        }
        return true;
    }

}

?>