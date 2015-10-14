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

  public function __construct($structure, $options=[])
  {
    $this->book_structure = $structure;
    $this->version = isset($options['version']) ? $options['version'] : 'thin';
    $this->manifest = self::get_manifest_template();
    $this->link_template = $this->get_lti_link_template();
    if( isset($options['use_page_name_launch_url']) && $options['use_page_name_launch_url']){
      $this->use_page_name_launch_url = true;
    }
    $this->options = $options;
  }

  public function build_manifest()
  {
    $this->manifest = str_replace('{course_name}', get_bloginfo('name'), $this->manifest);
    $this->manifest = str_replace('{course_description}', get_bloginfo('description'), $this->manifest);
    $this->manifest = str_replace('{organization_items}', $this->item_parts(), $this->manifest);
    $this->manifest = str_replace('{resources}', $this->item_resources(), $this->manifest);
  }

  public function build_zip(){
    $this->tmp_file = tempnam("tmp","thincc");
    $zip = new \ZipArchive();
    $zip->open($this->tmp_file,\ZipArchive::OVERWRITE);

    $zip->addFromString('imsmanifest.xml', $this->manifest);
    if(!$this->options['inline']) {
      $this->add_lti_link_files($zip);
    }

    $zip->close();

    return $this->tmp_file;
  }

  public function cleanup(){
    if( isset($this->tmp_file) && get_resource_type($this->tmp_file) === 'file' ){
      unlink($this->tmp_file);
    }
  }

  function __destruct(){
    $this->cleanup();
  }

  private function item_parts()
  {
    $items = '';
    $template = <<<XML

    <item identifier="%s">
      <title>%s</title>
      %s
    </item>
XML;

    foreach ($this->book_structure['part'] as $part) {
      $item_pages = $this->item_pages($part);
      if($item_pages != ''){
        $items .= sprintf($template, $this->identifier($part, "IM_"), $part['post_title'], $item_pages);
      }
    }

    return $items;
  }

  private function item_pages($part)
  {
    $items = '';
    $template = <<<XML

        <item identifier="%s" identifierref="%s">
          <title>%s</title>
        </item>
XML;

    // The part link comes first
    if($this->options['include_parts']) {
      $items .= sprintf($template, $this->identifier($part, "I_"), $this->identifier($part), $part['post_title']);
    }

    // Then each of the pages
    foreach ($part['chapters'] as $chapter) {
      if($this->export_page($chapter)) {
        $items .= sprintf($template, $this->identifier($chapter, "I_"), $this->identifier($chapter), $chapter['post_title']);
      }
    }

    return $items;
  }

  private function item_resources()
  {
    if($this->options['inline']) {
      return $this->inline_lti_resources();
    }else {
      return $this->referenced_lti_resources();
    }
  }

  private function get_base_url(){
    $blog_id = get_current_blog_id();
    if( $this->options['use_custom_vars'] ){
      return get_site_url(1) . '/api/lti/' . $blog_id;
    } else if ( $this->use_page_name_launch_url ) {
      return get_site_url(1) . '/api/lti/' . $blog_id . '?page_title=chapter%%2F%s';
    }else{
      return get_site_url(1) . '/api/lti/' . $blog_id . '?page_id=%s';
    }
  }

  private function create_launch_url($page){
    if( $this->options['use_custom_vars'] ) {
      return $this->get_base_url();
    } else if ( $this->use_page_name_launch_url ) {
      return sprintf($this->get_base_url(), $page['post_name']);
    }else{
      return sprintf($this->get_base_url(), $page['ID']);
    }
  }

  private function template_check($page) {
    $template = "";
    $guids_array = get_post_meta($page['ID'], 'CANDELA_OUTCOMES_GUID');

    if(!empty($guids_array)){
      $template = <<<XML
          <resource identifier="%s" type="imsbasiclti_xmlv1p0">
            <metadata>
              <curriculumStandardsMetadataSet xmlns=/xsd/imscsmetadata_v1p0>
                <curriculumStandardsMetadata providerId="lumenlearning.com">
                  <setOfGUIDs>%s
                  </setOfGUIDs>
                </curriculumStandardsMetadata>
              </curriculumStandardsMetadataSet>
            </metadata>
            %s
          </resource>
XML;
    } else {
      $template = <<<XML
          <resource identifier="%s" type="imsbasiclti_xmlv1p0">
            %s
            %s
          </resource>
XML;
    }

    return $template;
  }

  private function inline_lti_resources(){
    $resources = "";

    foreach ($this->book_structure['part'] as $part) {

      if($this->options['include_parts']) {
        if($this->options['include_guids']){
          $resources .= sprintf("\n" . $this->template_check($part), $this->identifier($part), $this->guids_xml($part), $this->link_xml($part));
        } else {
          $resources .= sprintf("\n" . $this->template_check($part), $this->identifier($part), '', $this->link_xml($part));
        }
      }

      foreach ($part['chapters'] as $chapter) {
        if($this->export_page($chapter)){
          if($this->options['include_guids']){
            $resources .= sprintf("\n" . $this->template_check($chapter), $this->identifier($chapter), $this->guids_xml($chapter), $this->link_xml($chapter));
          } else {
            $resources .= sprintf("\n" . $this->template_check($chapter), $this->identifier($chapter), '', $this->link_xml($chapter));
          }
        }
      }
    }

    return $resources;
  }

  private function guids_xml($page){
    $guids = "";
    $guids_array = array($this->get_guids($page));
    $template = <<<XML
                    <labelledGUID>
                      <GUID>%s</GUID>
                    </labelledGUID>
XML;

    if(!empty($guids_array)){
      foreach ($guids_array as $data){
        foreach ($data as $guid){
          $guids .= sprintf("\n" . $template, $guid);
        }
      }

      return $guids;
    } else {
      return "";
    }
  }

  private function get_guids($page){
    if($this->guids_cache === null){
      $this->guids_cache = array();

      global $wpdb;
      $sql = $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", 'CANDELA_OUTCOMES_GUID' );

      foreach ( $wpdb->get_results( $sql, ARRAY_A ) as $val ) {
        // Strips whitespace from meta_value
        $val['meta_value'] = str_replace(' ', '', $val['meta_value']);
        // creates an array of values from a string split on comma
        $this->guids_cache[$val['post_id']] = explode(",", $val['meta_value']);
      }
    }

    return $this->guids_cache[$page['ID']] ? $this->guids_cache[$page['ID']] : [];
  }

  private function referenced_lti_resources(){
    $resources = '';
    $template = <<<XML
        <resource identifier="%s" type="imsbasiclti_xmlv1p0">
            %s
            <file href="%s.xml"/>
        </resource>
XML;
    foreach ($this->book_structure['part'] as $part) {
      if($this->options['include_parts']) {
        $resources .= sprintf("\n" . $template, $this->identifier($part), $this->guids_xml($part), $this->identifier($part));
      }
      foreach ($part['chapters'] as $chapter) {
        if($this->export_page($chapter)) {
          $resources .= sprintf("\n" . $template, $this->identifier($chapter), $this->guids_xml($part), $this->identifier($chapter));
        }
      }
    }

    return $resources;
  }

  private function add_lti_link_files($zip){
    foreach ($this->book_structure['part'] as $part) {
      $add_part_guids = !empty($this->get_guids($part)) ? true : false; // This may be evil...
      if($this->options['include_parts']) {
        $zip->addFromString($this->identifier($part) . '.xml', $this->link_xml($part, $add_part_guids, true));
      }
      foreach ($part['chapters'] as $chapter) {
        $add_chapter_guids = !empty($this->get_guids($chapter)) ? true : false; // This may be evil...
        if($this->export_page($chapter)) {
          $zip->addFromString($this->identifier($chapter) . '.xml', $this->link_xml($chapter, $add_chapter_guids, true));
        }
      }
    }
  }

  private function link_xml($page, $add_guids=false, $add_xml_header=false){
    $launch_url = $this->create_launch_url($page);
    $template = "\n" . $this->link_template;
    $guids = "\n";

    $guids_array = $this->get_guids($page);

    if($add_guids){
      $guids .= "
<metadata>
  <curriculumStandardsMetadataSet xmlns=/xsd/imscsmetadata_v1p0>
    <curriculumStandardsMetadata providerId='lumenlearning.com'>
      <setOfGUIDs>";

      foreach ($guids_array as $guid){
        $guids .= "
        <labelledGUID>
          <GUID>" . $guid . "</GUID>
        </labelledGUID>";
      }

      $guids .= "
      </setOfGUIDs>
    </curriculumStandardsMetadata>
  </curriculumStandardsMetadataSet>
</metadata>
";

      $template = $guids . $template;
    }

    if($add_xml_header){
      $template = '<?xml version="1.0" encoding="UTF-8"?>' . $template;
    }

    if( $this->options['use_custom_vars'] ){
      $custom_variables = '<blti:custom><lticm:property name="page_id">' . $page['ID'] . '</lticm:property></blti:custom>';
    } else {
      $custom_variables = '';
    }

    return sprintf($template, $page['post_title'], $launch_url, $custom_variables);
  }

  private function export_page($page){
    if ($this->options['export_flagged_only']) {
      return $page['export'] == '1';
    } else {
      return true;
    }
  }

  public function __toString()
  {
    return $this->manifest;
  }

  private function get_manifest_template(){
    return file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . plugin_basename(self::$templates[$this->version]['manifest']));
  }

  private function get_lti_link_template(){
    return file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . plugin_basename(self::$templates[$this->version]['lti_link']));
  }

}
?>
