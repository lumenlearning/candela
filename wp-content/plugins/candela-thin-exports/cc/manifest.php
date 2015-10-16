<?php
namespace CC;
require_once('base.php');

class Manifest extends Base
{

  private $book_structure = null;
  private $version = null;
  private $manifest = null;
  private $link_template = null;
  private $is_inline = false;
  private $use_page_name_launch_url = false;
  private $options = null;
  private $guids_cache = null;

  private $tmp_file = null;

  private static $templates = [
    "1.1" => [
      'manifest' => '/templates/cc_1_1/manifest.xml',
      'lti_link' => '/templates/cc_1_2/lti_link.xml',
    ],
    "1.2" => [
      'manifest' => '/templates/cc_1_2/manifest.xml',
      'lti_link' => '/templates/cc_1_2/lti_link.xml',
    ],
    "1.3" => [
      'manifest' => '/templates/cc_1_3/manifest.xml',
      'lti_link' => '/templates/cc_1_3/lti_link.xml',
    ],
    "thin" => [
      'manifest' => '/templates/thin/manifest.xml',
      'lti_link' => '/templates/cc_1_3/lti_link.xml',
    ],
  ];

  public static $available_options = ['inline', 'include_fm', 'include_bm', 'export_flagged_only',
                                      'use_custom_vars', 'include_parts', 'include_guids'];

  public function __construct($structure, $options=[]) {
    $this->book_structure = $structure;
    $this->version = isset($options['version']) ? $options['version'] : 'thin';
    $this->manifest = self::get_manifest_template();
    $this->link_template = $this->get_lti_link_template();
    if (isset($options['use_page_name_launch_url']) && $options['use_page_name_launch_url']) {
      $this->use_page_name_launch_url = true;
    }
    $this->options = $options;
  }

  public function build_manifest() {
    $this->manifest = str_replace('{course_name}', get_bloginfo('name'), $this->manifest);
    $this->manifest = str_replace('{course_description}', get_bloginfo('description'), $this->manifest);
    $this->manifest = str_replace('{organization_items}', $this->item_parts(), $this->manifest);
    $this->manifest = str_replace('{resources}', $this->item_resources(), $this->manifest);
  }

  public function build_zip() {
    $this->tmp_file = tempnam("tmp","thincc");
    $zip = new \ZipArchive();
    $zip->open($this->tmp_file,\ZipArchive::OVERWRITE);

    $zip->addFromString('imsmanifest.xml', $this->manifest);
    if (!$this->options['inline']) {
      $this->add_lti_link_files($zip);
    }

    $zip->close();

    return $this->tmp_file;
  }

  public function cleanup() {
    if( isset($this->tmp_file) && get_resource_type($this->tmp_file) === 'file' ){
      unlink($this->tmp_file);
    }
  }

  function __destruct() {
    $this->cleanup();
  }

  private function item_parts() {
    $items = '';
    $template = <<<XML

    <item identifier="%s">
      <title>%s</title>
      %s
    </item>
XML;

    foreach ($this->book_structure['part'] as $part) {
      $item_pages = $this->item_pages($part);
      if ($item_pages != '') {
        $items .= sprintf($template, $this->identifier($part, "IM_"), $part['post_title'], $item_pages);
      }
    }
    return $items;
  }

  private function item_pages($part) {
    $items = '';
    $template = <<<XML

        <item identifier="%s" identifierref="%s">
          <title>%s</title>
        </item>
XML;

    // The part link comes first
    if ($this->options['include_parts']) {
      $items .= sprintf($template, $this->identifier($part, "I_"), $this->identifier($part), $part['post_title']);
    }

    // Then each of the pages
    foreach ($part['chapters'] as $chapter) {
      if ($this->export_page($chapter)) {
        $items .= sprintf($template, $this->identifier($chapter, "I_"), $this->identifier($chapter), $chapter['post_title']);
      }
    }
    return $items;
  }

  private function item_resources() {
    return $this->lti_resources();
  }

  private function get_base_url() {
    $blog_id = get_current_blog_id();
    if($this->options['use_custom_vars']) {
      return get_site_url(1) . '/api/lti/' . $blog_id;
    }
    else if ($this->use_page_name_launch_url) {
      return get_site_url(1) . '/api/lti/' . $blog_id . '?page_title=chapter%%2F%s';
    }
    else {
      return get_site_url(1) . '/api/lti/' . $blog_id . '?page_id=%s';
    }
  }

  private function create_launch_url($page) {
    if ($this->options['use_custom_vars']) {
      return $this->get_base_url();
    }
    else if ($this->use_page_name_launch_url) {
      return sprintf($this->get_base_url(), $page['post_name']);
    }
    else {
      return sprintf($this->get_base_url(), $page['ID']);
    }
  }

  private function lti_resources() {
    $resources = '';
    $template = <<<XML

        <resource identifier="%s" type="imsbasiclti_xmlv1p0">%s
          %s
        </resource>
XML;

    foreach ($this->book_structure['front-matter'] as $fm) {
      if ($this->options['include_fm']) {
        $resources .= sprintf($template, $this->identifier($fm), $this->guid_xml($fm), $this->lti_resource_helper($fm));
      }
    }
    foreach ($this->book_structure['part'] as $part) {
      if ($this->options['include_parts']) {
        $resources .= sprintf($template, $this->identifier($part), $this->guid_xml($part), $this->lti_resource_helper($part));
      }
      foreach ($part['chapters'] as $chapter) {
        if ($this->export_page($chapter)) {
          $resources .= sprintf($template, $this->identifier($chapter), $this->guid_xml($chapter), $this->lti_resource_helper($chapter));
        }
      }
    }
    foreach ($this->book_structure['back-matter'] as $bm) {
      if ($this->options['include_bm']) {
        $resources .= sprintf($template, $this->identifier($bm), $this->guid_xml($bm), $this->lti_resource_helper($bm));
      }
    }
    return $resources;
  }

  private function guid_xml($page) {
    if ($this->options['include_guids'] && !empty($this->get_guids($page))) {
      $template = <<<XML

            <metadata>
              <curriculumStandardsMetadataSet xmlns="/xsd/imscsmetadata_v1p0">
                <curriculumStandardsMetadata providerId="lumenlearning.com">
                  <setOfGUIDs>%s
                  </setOfGUIDs>
                </curriculumStandardsMetadata>
              </curriculumStandardsMetadataSet>
            </metadata>
XML;
      return sprintf($template, $this->guids_xml($page));
    }
    else {
      return '';
    }
  }

  private function lti_resource_helper($page) {
    if(!$this->options['inline']) {
      return '<file href="' . $this->identifier($page) . '.xml"/>';
    }
    else if($this->options['inline']) {
      return $this->link_xml($page);
    }
  }

  private function guids_xml($page) {
    $guids = '';
    $guids_array = array($this->get_guids($page));
    $template = <<<XML
                    <labelledGUID>
                      <GUID>%s</GUID>
                    </labelledGUID>
XML;

    if (!empty($guids_array)) {
      foreach ($guids_array as $data) {
        foreach ($data as $guid){
          $guids .= sprintf("\n" . $template, $guid);
        }
      }
      return $guids;
    }
    else {
      return '';
    }
  }

  private function get_guids($page) {
    if ($this->guids_cache === null) {
      global $wpdb;
      $sql = $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", 'CANDELA_OUTCOMES_GUID' );

      foreach ($wpdb->get_results( $sql, ARRAY_A ) as $val) {
        $val['meta_value'] = str_replace(' ', '', $val['meta_value']);
        $this->guids_cache[$val['post_id']] = explode(",", $val['meta_value']);
      }
    }
    return $this->guids_cache[$page['ID']] ? $this->guids_cache[$page['ID']] : [];
  }

  private function add_lti_link_files($zip) {
    foreach ($this->book_structure['part'] as $part) {
      if ($this->options['include_parts']) {
        $zip->addFromString($this->identifier($part) . '.xml', $this->link_xml($part, true));
      }
      foreach ($part['chapters'] as $chapter) {
        if ($this->export_page($chapter)) {
          $zip->addFromString($this->identifier($chapter) . '.xml', $this->link_xml($chapter, true));
        }
      }
    }
  }

  private function link_xml($page, $add_xml_header=false) {
    $launch_url = $this->create_launch_url($page);
    $template = "\n" . $this->link_template;

    if ($add_xml_header) {
      $template = '<?xml version="1.0" encoding="UTF-8"?>' . $template;
    }

    if ($this->options['use_custom_vars']) {
      $custom_variables = '<blti:custom><lticm:property name="page_id">' . $page['ID'] . '</lticm:property></blti:custom>';
    }
    else {
      $custom_variables = '';
    }
    return sprintf($template, $page['post_title'], $launch_url, $custom_variables);
  }

  private function export_page($page) {
    if ($this->options['export_flagged_only']) {
      return $page['export'] == '1';
    }
    else {
      return true;
    }
  }

  public function __toString() {
    return $this->manifest;
  }

  private function get_manifest_template() {
    return file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . plugin_basename(self::$templates[$this->version]['manifest']));
  }

  private function get_lti_link_template() {
    return file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . plugin_basename(self::$templates[$this->version]['lti_link']));
  }
}
?>
