<?php
/*
 * -File        $Id: regex.validator.php,v 1.3 2005/04/29 01:49:31 amadeus Exp $
 * -License     LGPL (http://www.gnu.org/copyleft/lesser.html)
 * -Copyright   2002, Nexista
 * -Author      joshua savage, 
 */

/**
 * @package     Nexista
 * @subpackage  Validators
 * @author      Joshua Savage <>
 */
 
/**
 * This  validator is used to check data using
 * a Regular Expression passed as a parameter
 *
 * @package     Nexista
 * @subpackage  Validators
 */


class RegexValidator extends Validator
{

    /**
     * Function parameter array
     *
     * @var array
     */

    protected $params = array(
        'var' => '',    //required - name of flow var to regexp
        'regex' => ''   //required - regexp to apply
        );

    
    /**
     * Validator error message
     *
     * @var     string
     */

    protected $message = "is not acceptable";


    /**
     * Apply regexp to data
     *
     * @return  boolean     success
     */

    public function main()
    {
        $data = Path::get($this->params['var'], 'flow');
        if(!empty($data))
        {
            $this->result = preg_match($this->params['regex'], $data);
            return true;
        }
        $this->setEmpty();
        return true;
    }

} //end class
?>
