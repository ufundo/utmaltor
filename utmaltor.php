<?php

require_once 'utmaltor.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function utmaltor_civicrm_config(&$config) {
  _utmaltor_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function utmaltor_civicrm_xmlMenu(&$files) {
  _utmaltor_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function utmaltor_civicrm_install() {
  _utmaltor_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function utmaltor_civicrm_uninstall() {
  _utmaltor_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function utmaltor_civicrm_enable() {
  _utmaltor_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function utmaltor_civicrm_disable() {
  _utmaltor_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function utmaltor_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _utmaltor_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function utmaltor_civicrm_managed(&$entities) {
  _utmaltor_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function utmaltor_civicrm_caseTypes(&$caseTypes) {
  _utmaltor_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function utmaltor_civicrm_angularModules(&$angularModules) {
  _utmaltor_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function utmaltor_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _utmaltor_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_post().
 */
function utmaltor_civicrm_post($op, $objectName, $id, &$params) {
  if ($objectName == 'Mailing' && $op = 'edit') {

    $utmParams = ['mailing_id' => $id, 'campaign_id' => $params->campaign_id, 'subject' => $params->subject];
    _utmaltor_mod_utm_params($utmParams);

    $newBodyHtml = _utmaltor_findUrls($params->body_html, $utmParams);
    $newBodyText = _utmaltor_findUrls($params->body_text, $utmParams);
    if ($params->body_html != $newBodyHtml) {
      $sql = 'UPDATE civicrm_mailing SET body_html = %1, body_text = %2, modified_date = modified_date WHERE id = %3';
      $sqlParams = [
        1 => [$newBodyHtml, 'String'],
        2 => [$newBodyText, 'String'],
        3 => [$id, 'Integer']
      ];
      \CRM_Core_DAO::executeQuery($sql, $sqlParams);
    }
  }
}

/**
 * Implements hook_civicrm_alterMailContent().
 */
function utmaltor_civicrm_alterMailContent(&$content) {
  /**
   * in CRM_Mailing_BAO_Mailing->getTemplates() method are
   *   $this->templates['mailingID'] = $this->id;
   * + $this->templates['campaign_id'] = $this->campaign_id;
   *
   * @link https://github.com/civicrm/civicrm-core/pull/16629
   */

  $utmParams = ['mailing_id' => $content['mailingID'], 'campaign_id' => $content['campaign_id'], 'subject' => $content['subject']];
  _utmaltor_mod_utm_params($utmParams);

  $content['html'] = _utmaltor_findUrls($content['html'], $utmParams);
  $content['text'] = _utmaltor_findUrls($content['text'], $utmParams);
}

/**
 * This identifies and modifies the URLs found in the passed $text.
 *
 * @param string $text string The body of the email to search for URLs.
 * @param array $params Parameters to pass to the Smarty singleton for rendering
 *   as a token.
 *
 * @return string|string[]|null
 */
function _utmaltor_findUrls($text, $params) {
  $domains = Civi::settings()->get('utmaltor_domains');
  $domains = str_replace('.', '\.', $domains);
  $re = '/(https?\:\/\/(' . $domains . ')[^\s"<]*)/imu';
  $callback = new CRM_Utmaltor_Logic_Alter($params);
  $text = preg_replace_callback($re, [$callback, 'url'], $text);

  return $text;
}


function _utmaltor_mod_utm_params(&$params) {

   $find_campaign_name = \Civi\Api4\Campaign::get()
    ->addSelect('name')
    ->addWhere('id', '=', $params['campaign_id'])
    ->setLimit(1)
    ->execute();

  $params['campaign_name'] = $find_campaign_name[0]['name'];

  //strip non-ASCII from mailing subject
  $params['subject'] =  preg_replace("/[^A-Za-z0-9]/", '_', $params['subject']);

}
