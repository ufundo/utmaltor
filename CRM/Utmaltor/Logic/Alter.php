<?php

class CRM_Utmaltor_Logic_Alter {

  private $smarty;

  public function __construct($params) {
    $this->smarty = CRM_Utmaltor_Logic_Smarty::singleton($params);
  }

  public function url($urlMatches) {
    $url = $urlMatches[1];
    $url = $this->fixUrl($url);

    // let's not mess with images or links using civi router
    if (preg_match('/\.(png|jpg|jpeg|gif|css)[\'"]?$/i', $url)
      or substr_count($url, 'civicrm/extern/')
      or substr_count($url, 'civicrm/mailing/')
      or ($url[0] === '#')
    ) {
      return $url;
    }

    $url = $this->alterUtm('source', $url, $this->smarty);
    $url = $this->alterUtm('medium', $url, $this->smarty);
    $url = $this->alterUtm('campaign', $url, $this->smarty);
    $url = $this->alterUtm('content', $url, $this->smarty);

    return $url;
  }

  private function fixUrl($url) {
    return str_replace('&amp;', '&', $url);
  }

  private function alterUtm($key, $url, CRM_Utmaltor_Logic_Smarty $smarty) {
    $value = Civi::settings()->get('utmaltor_' . $key);
    $value = $smarty->parse($value);
    $override = (boolean) Civi::settings()->get('utmaltor_' . $key . '_override');
    return $this->setKey($url, 'utm_' . $key, $value, $override);
  }

  private function setKey($url, $key, $value, $override = FALSE) {
    if ($override) {
      return $this->setValue($url, $key, $value);
    }
    if ((strpos($url, $key) === FALSE) || (strpos($url, $key) !== FALSE && !$this->getValue($url, $key))) {
      return $this->setValue($url, $key, $value);
    }
    return $url;
  }

  private function getValue($url, $key) {
    $query = parse_url($url, PHP_URL_QUERY);
    parse_str($query, $arr);
    if (array_key_exists($key, $arr)) {
      return trim($arr[$key]);
    }
    return "";
  }

  private function setValue($url, $key, $value) {
    $urlParts = parse_url($url);
    if (array_key_exists('query', $urlParts)) {
      parse_str($urlParts['query'], $query);
    }
    else {
      $query = array();
    }
    if (!array_key_exists('path', $urlParts)) {
      $urlParts['path'] = '/';
    }
    $urlParts['query'] = http_build_query($query ? array_merge($query, array($key => $value)) : array($key => $value));
    $newUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'] . '?' . $urlParts['query'];
    if (array_key_exists('fragment', $urlParts) && $urlParts['fragment']) {
      $newUrl .= '#' . $urlParts['fragment'];
    }
    $tokens = array(
      '%7B' => '{',
      '%7D' => '}',
      '{contact_checksum}=' => '{contact.checksum}', // #3 Token {contact_checksum} breaks down links
      '{contact.checksum}=' => '{contact.checksum}', // #3 Token {contact_checksum} breaks down links
    );
    $newUrl = str_replace(array_keys($tokens), array_values($tokens), $newUrl);
    return $newUrl;
  }

}
