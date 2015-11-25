<?php
class CosmoCommerce_Updates_Block_Adminhtml_System_Config_Form_Field_Version extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
    
    
        $_htmlId = $element->getHtmlId();
        // Get the default HTML for this option
        //$html = parent::_getElementHtml($element);
        $html="";
        $modules=Mage::getConfig()->getNode()->modules;
        $cosmomodules=array();
        foreach($modules[0] as $module){
        
            if (strpos($module->getName(), 'CosmoCommerce') !== FALSE){
                
                $cosmomodules[]=($module);
            }
        }
        foreach($cosmomodules as $cosmomodule){
            $html.= $cosmomodule->getName()."  <b>".$cosmomodule->version."</b><br />";
        }
        //print_r($activityPath);
        return $html;
            /*
            $html.="<br />";
            chdir(Mage::getBaseDir('base'));
            $last_line = exec('/var/www/bin/modman status', $output,$retval);
            $html.=implode("<br />",$output);
            */
        
        
    }
}