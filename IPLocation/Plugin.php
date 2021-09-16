<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 显示评论IP所对应的真实地址
 * 
 * @package IPLocation
 * @author joyqi
 * @version 1.0.0
 * @link https://joyqi.com/typecho/iplocation-plugin.html
 */
class IPLocation_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Comments_Admin')->callIp = array('IPLocation_Plugin', 'location');
        Typecho_Plugin::factory('Widget_Comments_Archive')->callLocation = array('IPLocation_Plugin', 'location');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 插件实现方法
     * 
     * @access public
     * @param Typecho_Widget $comments 评论
     * @return void
     */
    public static function location($comments)
    {
        $addresses = IPLocation_IP::find($comments->ip);
        $address = 'unknown';

        if (!empty($addresses)) {
            $addresses = array_unique($addresses);
            $address = implode('', $addresses);
        }

        if ($comments instanceof Widget_Comments_Admin) {
            echo $comments->ip . '<br>' . $address;
        } else {
            echo $address;
        }
    }
}
