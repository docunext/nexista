<?php
/*
 * -File        plugin.builder.php
 * -License     LGPL (http://www.gnu.org/copyleft/lesser.html)
 * -Copyright   Savonix Corporation
 * -Author      Albert Lash
 */

/**
 * @package     Nexista
 * @subpackage  Builders
 * @author      Albert Lash
 */
 
/**
 * This class handles the tag by the same name in the sitemap building process
 *
 * @see         Builder
 * @package     Nexista
 * @subpackage  Builders
 */

class Nexista_PluginBuilder extends Nexista_Builder
{

    /**
     * Returns array of required files to insert in require_once fields
     *
     * @return    array Required files
     * @see
     */

    public function getRequired()
    {
        $req[] = Nexista_Config::get('./path/handlers').'plugin.handler.php';
        return $req;
    }

    /**
     * Returns start code for this tag.
     *
     * @return   string Final code to insert in gate
     * @see      Nexista_Builder::getCode()
     */

    public function getCodeStart()
    {

        $path = new Nexista_PathBuilder();
        if($this->action->hasAttribute('src'))
        {
            $code[] = $this->addErrorHandler("Nexista_PluginHandler::process(".
                $path->get($this->action->getAttribute('src'), 'string', JOIN_DOUBLE_QUOTE).", true)");

        }
        else
        {
            $code[] = $this->addErrorHandler("Nexista_PluginHandler::processItem('".
                $this->action->getAttribute('type')."',".$path->get($this->action->getAttribute('params'), 'string', JOIN_DOUBLE_QUOTE).", true)");
        }
        return implode(NX_BUILDER_LINEBREAK, $code);

    }
}

?>