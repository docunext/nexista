<?php
/**
 * -File        Xsl.Action.php
 * -Copyright   Savonix Corporation
 * -Author      Joshua Savage
 * -Author      Albert Lash
 *
 * PHP version 5
 *
 * @category  Nexista
 * @package   Nexista
 * @author    Albert Lash <albert.lash@gmail.com>
 * @copyright 0000 Nexista
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL
 * @link      http://www.nexista.org/
 */

/**
 * This action applies an xsl stylesheet to an xml string.
 *
 * The class accepts 3 arguments. 1) the flow variable name
 * containing the xml string, 2) the filename/path of the xsl
 * stylesheet to apply (from NX_PATH_APP) and 3) and optional
 * string that will be used as root tag along with the adding of
 * xml headers. This would be used when a string has tags that that
 * should be transformed by a stylesheet but the string does not have
 * headers/root tags.
 *
 * @package     Nexista
 * @subpackage  Actions
 */

class Nexista_XslAction extends Nexista_Action
{

    /**
     * Function parameter array
     *
     * @var array
     */

    protected  $params = array(
        'xsl' => '',    //required - xsl file
        'new_node' => '' // required - new node to create for the output
        );

    /**
     * Applies action
     *
     * @return  boolean success
     */

    protected function main()
    {

        $flow = Nexista_Flow::Singleton('Nexista_Flow');
        $file_path = Nexista_Path::parseInlineFlow($this->params['xsl']);
        $xslfile = NX_PATH_APPS.$file_path;
        if(!is_file($xslfile)) {
            Nexista_Error::init('XSL Action - file unavailable: '.$xslfile, NX_ERROR_FATAL);
        }

        $xsl = new DomDocument('1.0','UTF-8');
        $xsl->substituteEntities = false;
        $xsl->resolveExternals = false;
        $xslfilecontents .= file_get_contents($xslfile);
        $xsl->loadXML($xslfilecontents);
        $xsl->documentURI = $xslfile;

        $use_xslt_cache = "yes";
        if ($use_xslt_cache!="yes" || !class_exists('xsltCache')) {
            $xslHandler = new XsltProcessor;
        } else {
            $xslHandler = new xsltCache;
        }

        $xslHandler->importStyleSheet($xsl);

        $my_output = $xslHandler->transformToXML($flow->flowDocument);
        if($my_output === false)
        {
            Nexista_Error::init('XSL Action - Error processing XSL file: '.$xslfile, NX_ERROR_FATAL);
            return false;
        }

        $new_node = $this->params['new_node'];
        Nexista_Flow::add($new_node, $my_output);
        return true;
    }
} //end class

?>