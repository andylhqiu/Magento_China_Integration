<?php
class CosmoCommerce_Updates_Block_Adminhtml_System_Config_Fieldset_Updater
        extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    /**
     * Add custom css class
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getFrontendClass($element)
    {
        return parent::_getFrontendClass($element) . ' with-button '
            . ($this->_isEnabled($element) ? ' enabled' : '');
    }

    /**
     * Check whether current payment method is enabled
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return bool
     */
    protected function _isEnabled($element)
    {
        $groupConfig = $this->getGroup($element)->asArray();
        if (!extension_loaded("git2")){
            return false;
        }else{
            return true;
        }
    }

    /**
     * Return header title part of html for payment solution
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getHeaderTitleHtml($element)
    {
        $html = '<div id="cosmocommerce_modules" class="config-heading" ><div class="heading"><strong>' . $element->getLegend();

        $groupConfig = $this->getGroup($element)->asArray();
        if (!empty($groupConfig['learn_more_link'])) {
            $html .= '<a class="link-more" href="' . $groupConfig['learn_more_link'] . '" target="_blank">'
                . $this->__('Learn More') . '</a>';
        }
        if (!empty($groupConfig['demo_link'])) {
            $html .= '<a class="link-demo" href="' . $groupConfig['demo_link'] . '" target="_blank">'
                . $this->__('View Demo') . '</a>';
        }
        $html .= '</strong>';

        if ($element->getComment()) {
            $html .= '<span class="heading-intro">' . $element->getComment() . '</span>';
        }
        $html .= '</div>';

        
        if($this->_isEnabled($element)){
            $html .= '<div class="button-container"><button type="button"><span class="state-closed">'
                . $this->__('模块环境正常') . '</span></button></div></div>';
            
            //$version=Mage::getSingleton('adminhtml/config')->getSection('cosmocommerce')->modules->user;
            $user=(Mage::getStoreConfig('cosmocommerce/required_settings/user'));
            $pwd=(Mage::getStoreConfig('cosmocommerce/required_settings/pwd'));
            //print_r(Mage::getSingleton('adminhtml/config')->getSection('cosmocommerce')->groups->modules->fields->required_settings->fields->user->value);
            

            
            $html.='            
            <script type="text/javascript">
            //<![CDATA[
            function redirectToUpdate()
            {
                var url = "'.Mage::getSingleton("adminhtml/url")->getUrl("*/updates/update").'"+"?repo="+this.value;
                if (confirm("模块将会更新代码.")) {
                    if (Prototype.Browser.IE) {
                        var generateLink = new Element("a", {href: url});
                        $$("body")[0].insert(generateLink);
                        generateLink.click();
                    } else {
                        window.location.href = url;
                    }
                }
            }
            function redirectToUpdatef()
            {
                var url = "'.Mage::getSingleton("adminhtml/url")->getUrl("*/updates/updatef").'"+"?repo="+this.value;
                if (confirm("模块将会强制覆盖代码.")) {
                    if (Prototype.Browser.IE) {
                        var generateLink = new Element("a", {href: url});
                        $$("body")[0].insert(generateLink);
                        generateLink.click();
                    } else {
                        window.location.href = url;
                    }
                }
            }
            function redirectToCommit()
            {
                var url = "'.Mage::getSingleton("adminhtml/url")->getUrl("*/updates/commit").'"+"?repo="+this.value;
                var note = prompt("请输入记录这次版本的备注");
                url=url+"&note="+note;
                if (confirm("模块更新将会进行提交.")) {
                    if (Prototype.Browser.IE) {
                        var generateLink = new Element("a", {href: url});
                        $$("body")[0].insert(generateLink);
                        generateLink.click();
                    } else {
                        window.location.href = url;
                    }
                }
            }

            function disableGenerateButton(id)
            {
                var elem = $(id);
                elem.disabled = true;
                elem.addClassName("disabled");
            }


            $("cosmocommerce_modules").select("input").each(function(elem) {
                Event.observe($(elem.id), "change", disableGenerateButton(elem.id));
            });
            //]]>
            </script>';
            
            
            
                 
            $base_path = Mage::getBaseDir('base');
            $modman_path = Mage::getBaseDir('base').DS.'.modman';
            
            
            return $html;
            $html.="<ul  style='font-size: 11px;padding:10px;'>";    
            foreach(glob($modman_path."/*",GLOB_ONLYDIR) as $_subfolder){
            
                $repo = new Git2\Repository($_subfolder);
               
                $foldername= basename($_subfolder);
                //print_r($repo);
                $ref = Git2\Reference::lookup($repo, "refs/heads/master");
                //print_r(get_class_methods(new Git2\Repository($_subfolder)));
                //print_r(get_class_vars('Git2\Repository'));
                //print_r(get_object_vars($repo));
                //print_r($ref);
                //echo $ref->getName() . PHP_EOL;
                $version=$ref->getTarget();
                
                
                //$remoteref = Git2\Reference::lookup($repo, "refs/remotes/origin/master");
                //print_r($repo->lookup());
                //ini_set('display_errors',1);
                
                //以后考虑要做一个模块，定时把github版本记录下来。不用经常远程查询
                
                
                
                
               //Set maximum age of cache file before refreshing it
                $cacheLife = 1800; // in seconds
                $cacheFileName="/tmp/".$foldername;
                if ((!file_exists($cacheFileName) or (time() - filemtime($cacheFileName) >= $cacheLife))){
                    $ch =  curl_init("https://api.github.com/repos/cosmocommerce/".$foldername."/commits");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $result=curl_exec($ch);
                    $content = json_decode($result);
                    if($content){
                        $remoteversion=$content[0]->sha;
                        file_put_contents($cacheFileName, ($result));
                    }else{
                        $remoteversion='error';
                    
                    }
                    //$content=json_decode(file_get_contents("https://api.github.com/repos/cosmocommerce/".$foldername."/commits"));
                    //$remoteversion=$content[0]->sha;
                    //file_put_contents($cacheFileName, ($result));
                    
                    //$remoteversion='af';
                    
                        $remoteversion='error';
                }
                else{
                    $content = json_decode(file_get_contents($cacheFileName));
                    if($content){
                        $remoteversion=$content[0]->sha;
                    }else{
                        $remoteversion='error';
                    }
                }
                
                
                
                $config = new Git2\Config($_subfolder."/.git/config");
                $remoteurl=( $config->get("remote")['origin']['url'] );

                $path = $_subfolder;
                $output="";
                $class="";
                chdir($path);
                $last_line = exec(escapeshellcmd('git status'), $output,$retval);
                if($last_line=="nothing to commit (working directory clean)"){
                    $class="success";
                }else{
                    $class="fail";
                }
                if($last_line=='no changes added to commit (use "git add" and/or "git commit -a")'){
                    $class="fail";
                }
                $html .= '<li>';
                $html .= '<div style="float:left">';
                
                $html .= '<button type="button" class="scalable '.$class.'"  style=""><span><span><span>'.$foldername.'</span></span></span></button> <button id="updateBtn'.$foldername.'" value="'.$foldername.'" type="button" class="scalable save" onclick="" style=""><span><span><span>更新</span></span></span></button> ';
                $html .= '<button id="commitBtn'.$foldername.'" value="'.$foldername.'" type="button" class="scalable save" onclick="" style=""><span><span><span>提交</span></span></span></button> </div>';
                
                $html .= '<div style="float:right;"><button id="updatefBtn'.$foldername.'" value="'.$foldername.'" type="button" class="scalable save" onclick="" style=""><span><span><span>强制更新</span></span></span></button> </div>';
                $html .= '
                <script type="text/javascript">
                //<![CDATA[
                Event.observe("updateBtn'.$foldername.'", "click", redirectToUpdate);
                Event.observe("updatefBtn'.$foldername.'", "click", redirectToUpdatef);
                Event.observe("commitBtn'.$foldername.'", "click", redirectToCommit);
                //]]>
                </script>';
                
                $html .= '<div style="clear:both;">';
                if($version==$remoteversion){
                    $html .= '版本一致:<br />'.$version.'<br />'.date('Y-m-d h:j:s',filemtime($_subfolder))."<br />";
                }else{
                    $html .= '本地版本:<br />'.$version.'<br />'.date('Y m-d h:j:s',filemtime($_subfolder))."<br />";
                    $html .= '远程版本:<br />'.$remoteversion."<br />";
                }
                    if($class=='fail'){
                        $html .= '修改说明:<br />';
                        $html .= implode('<br />',$output)."<br />";
                    }
                //$html .= $retval."<br />";
                $html .="</div>";
                //$html .= "<b>".$last_line."</b><br />";
                $html .= '</li>';
            
            }
            $html.="</ul>";
            
            
        }else{
            $html .= '<div class="button-container"><button type="button"><span class="state-opened">'
                . $this->__('模块环境缺失') . '</span></button></div></div>';
            
            
        }

        return $html;
    }

    /**
     * Return header comment part of html for payment solution
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getHeaderCommentHtml($element)
    {
        return '';
    }

    /**
     * Get collapsed state on-load
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return bool
     */
    protected function _getCollapseState($element)
    {
        return false;
    }
}
