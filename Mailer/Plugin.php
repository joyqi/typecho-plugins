<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/PHPMailer.php';

/**
 * 将评论发送至相关邮箱
 * 
 * @package Mailer
 * @author joyqi
 * @version 1.1.0
 * @link https://joyqi.com/typecho/mailer-plugin.html
 */
class Mailer_Plugin implements Typecho_Plugin_Interface
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
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('Mailer_Plugin', 'send');
        Typecho_Plugin::factory('Widget_Service')->sendMail = array('Mailer_Plugin', 'sendMail');
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
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text('host', NULL, '', _t('邮件服务器')));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select('port', array(25 => 25, 465 => 465, 587 => 587, 2525 => 2525), 587, _t('端口号')));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select('secure', array('tls' => 'tls', 'ssl' => 'ssl'), 'ssl', _t('连接加密方式')));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio('auth', array(1 => '是', 0 => '否'), 0, _t('启用身份验证')));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text('user', NULL, '', _t('用户名'), _t('启用身份验证后有效')));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text('password', NULL, '', _t('密码'), _t('启用身份验证后有效')));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text('from', NULL, '', _t('发送人邮箱')));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio('reply', array(1 => '是', 0 => '否'), 0, _t('发送回复'), _t('如果评论人收到回复, 那么给他也发送一封邮件')));
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Textarea('template', NULL, "你收到了关于文章《{title}》来自 {user} 的评论\n{url}\n\n以下是评论详情:\n\n{text}", _t('邮件正文模版')));
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /**
     * 检查参数
     *
     * @param array $settings
     * @return string
     */
    public static function configCheck(array $settings)
    {
        if (!empty($settings['host'])) {
            $smtp = new SMTP;
            $smtp->setTimeout(10);

            if (!$smtp->connect($settings['host'], $settings['port'])) {
                return _t('邮件服务器连接失败');
            }

            if (!$smtp->hello(gethostname())) {
                return _t('向邮件服务器发送指令失败');
            }

            $e = $smtp->getServerExtList();

            if (is_array($e) && array_key_exists('STARTTLS', $e)) {
                if ($settings['secure'] != 'tls') {
                    return _t('邮件服务器要求使用TLS加密');
                }

                $tls = $smtp->startTLS();

                if (!$tls) {
                    return _t('无法用TLS连接邮件服务器');
                }

                if (!$smtp->hello(gethostname())) {
                    return _t('向邮件服务器发送指令失败');
                }

                $e = $smtp->getServerExtList();
            }

            if (is_array($e) && array_key_exists('AUTH', $e)) {
                if (!$settings['auth']) {
                    return _t('邮件服务器要求启用身份验证');
                }

                if (!$smtp->authenticate($settings['user'], $settings['password'])) {
                    return _t('身份验证失败, 请检查您的用户名或者密码');
                }
            }
        }
    }
    
    /**
     * 异步回调
     * 
     * @access public
     * @param int $commentId 评论id
     * @return void
     */
    public static function sendMail($commentId)
    {
        $options = Helper::options();
        $pluginOptions = $options->plugin('Mailer');
        $comment = Helper::widgetById('comments', $commentId);

        if (empty($pluginOptions->host)) {
            return;
        }

        if (!$comment->have() || empty($comment->mail)) {
            return;
        }
        
        $mail = new PHPMailer(false);

        $mail->isSMTP();
        $mail->Host = $pluginOptions->host;
        $mail->SMTPAuth = !!$pluginOptions->auth;
        $mail->Username = $pluginOptions->user;
        $mail->Password = $pluginOptions->password;
        $mail->SMTPSecure = $pluginOptions->secure;
        $mail->Port = $pluginOptions->port;
        $mail->getSMTPInstance()->setTimeout(10);

        $mail->CharSet = 'utf-8';
        $mail->setFrom($pluginOptions->from, $options->title);
        $mail->Subject = _t('来自文章 %s 的评论', $comment->title);
        $mail->Body = str_replace(array('{user}', '{title}', '{url}', '{text}'),
            array($comment->author, $comment->title, $comment->permalink, $comment->text), $pluginOptions->template);

        $post = Helper::widgetById('contents', $comment->cid);
        $mail->addAddress($post->author->mail, $post->author->name);

        if ($pluginOptions->reply && $comment->parent) {
            $parent = Helper::widgetById('comments', $comment->parent);

            if (!empty($parent->mail) && $parent->mail != $post->author->mail) {
                $mail->addAddress($parent->mail, $parent->author);
            }
        }

        $mail->send();
    }

    /**
     * 评论回调
     *
     * @param $comment
     */
    public static function send($comment)
    {
        Helper::requestService('sendMail', $comment->coid);
    }
}
