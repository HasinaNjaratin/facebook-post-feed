<?php

namespace Drupal\facebook_post_feed\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Facebook Feed Block' block.
 *
 * @Block(
 *   id = "facebook_block",
 *   admin_label = @Translation("Facebook Feed Block"),
 *   category = @Translation("Custom")
 * )
 */
class FacebookBlock extends BlockBase {

  /**
   * {@inheritdoc}
   *
   * This method sets the block default configuration. This configuration
   * determines the block's behavior when a block is initially placed in a
   * region. Default values for the block configuration form should be added to
   * the configuration array. System default configurations are assembled in
   * BlockBase::__construct() e.g. cache setting and block title visibility.
   *
   * @see \Drupal\block\BlockBase::__construct()
   */
  public function defaultConfiguration() {
    return [
      'fb_feed_post_number' => '5',
    ];
  }

  /**
  * {@inheritdoc}
  *
  * This method defines form elements for custom block configuration. Standard
  * block configuration fields are added by BlockBase::buildConfigurationForm()
  * (block title and title visibility) and BlockFormController::form() (block
  * visibility settings).
  *
  * @see \Drupal\block\BlockBase::buildConfigurationForm()
  * @see \Drupal\block\BlockFormController::form()
  */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['facebook_post_feed_block_page_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page Id :'),
      '#description' => $this->t('Your Facebook Page Id'),
      '#default_value' => $this->configuration['fb_feed_page_id'],
    ];

    $form['facebook_post_feed_block_app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App Id :'),
      '#required' => TRUE,
      '#description' => $this->t('Facebook App Id'),
      '#default_value' => $this->configuration['fb_feed_app_id'],
    ];

    $form['facebook_post_feed_block_app_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('App Secret :'),
      '#required' => TRUE,
      '#description' => $this->t('Facebook App Secret'),
      '#default_value' => $this->configuration['fb_feed_secret_id'],
    ];

    $form['facebook_post_feed_block_post_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Post Number :'),
      '#description' => $this->t('The number of last post to show'),
      '#default_value' => $this->configuration['fb_feed_post_number'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * This method processes the blockForm() form fields when the block
   * configuration form is submitted.
   *
   * The blockValidate() method can be used to validate the form submission.
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration = [
      'fb_feed_page_id' => $form_state->getValue('facebook_post_feed_block_page_id'),
      'fb_feed_app_id' => $form_state->getValue('facebook_post_feed_block_app_id'),
      'fb_feed_secret_id' => $form_state->getValue('facebook_post_feed_block_app_secret'),
      'fb_feed_post_number' => $form_state->getValue('facebook_post_feed_block_post_number')
    ];

  }


  /**
   * {@inheritdoc}
   *
   * The return value of the build() method is a renderable array. Returning an
   * empty array will result in empty block contents. The front end will not
   * display empty blocks.
   */
  public function build() {
    // We return an empty array on purpose. The block will thus not be rendered
    // on the site. See BlockExampleTest::testBlockExampleBasic().

    return array(
      '#theme' => 'facebook_post_feed_list',
      '#posts' => $this->getPostFeed(),
    );
  }

  /**
   * Send a request on facebook App to get last post
   */
  protected function getPostFeed() {
    $token = $this->getToken();
    $pageFBID = $this->configuration['fb_feed_page_id'];
    $number = $this->configuration['fb_feed_post_number'];
    $get_data = [];

    if($pageFBID && $token) {
      $response = @file_get_contents("https://graph.facebook.com/v2.10/$pageFBID?fields=feed.limit($number){created_time,message,picture},name,link&access_token=".$token);

      $get_data = json_decode($response,true);
      
      if(isset($get_data['feed'])) $get_data['feed'] = self::prepareData($get_data['feed']);
    }

    return $get_data;
  }

  /**
   * get Facebook token to access on app API
   * The token is build by App ID an Secret Id
   */
  protected function getToken() {
    return $this->configuration['fb_feed_app_id']."|".$this->configuration['fb_feed_secret_id'];
  }


  protected static function prepareData($get_data) {
    $datas = [];
    $default = ['picture' => '', 'created_time' => '', 'message' => ''];

    if($get_data && is_array($get_data)) {
      foreach ($get_data['data'] as $key => $data) {

        // Remove any unexpected fields.
        $data = array_intersect_key($data, $default);
        $date = \Drupal::service('date.formatter')->formatInterval(REQUEST_TIME - strtotime($data['created_time']));
        $data['created_time'] = 'Il y a '. self::translateDate($date);
        $data['message'] = self::_substrwords($data['message'],90);
        $datas[] = $data;
      }
    }

    return $datas;
  }

  protected static function _substrwords($text, $maxchar, $end='...') {
    if (strlen($text) > $maxchar || $text == '') {
      $text = substr($text, 0, $maxchar);
      $opened = strrpos($text,'<');
      $closed = strrpos($text,'>');
      if($opened > $closed) {
        $text = substr($text, 0, $opened);
      }

      // Get all opend element
      preg_match_all('/<([a-z].*?)>/', $text, $match);
      $open_matched = $match[1] ? $match[1] : [];

      // Get all closed element
      preg_match_all('/<\/(.*?)>/', $text, $match);
      $close_matched = $match[1] ? $match[1] : [];


      $last_html = '';
      $catch_balises = ['div', 'p', 'span', 'ul', 'li'];
      for ($i = count($open_matched)-1;  $i >= 0;  $i--) {
        $balise = $open_matched[$i];
        $find = FALSE;
        foreach ($catch_balises as $key => $catch_balise) {
          if (preg_match('/'.$catch_balise.'(\s|$|\.|\,)/', $balise)) {
            $balise = $catch_balise;
            $find = TRUE;
            break;
          }
        }

        if($find) {
          $key_find = array_search($balise, $close_matched);
          if($key_find !== false) {
            unset($close_matched[$key_find]);
          } else {
            $last_html = $last_html.'</'.$balise.'>';
          }
        }
      }
      $text .= ' ...' . $last_html;
    }

    return $text;
  }


  protected static function translateDate($date) {
    $date = str_replace("year","an",$date);
    $date = str_replace("months","mois",$date);
    $date = str_replace("month","mois",$date);
    $date = str_replace("week","semaine",$date);
    $date = str_replace("day","jour",$date);
    return str_replace("hour","heure",$date);
  }
}
