<?php
/**
 * This file is part of the collate extension
 * Copyright (C) 2015 Arent van Korlaar
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * @package MediaWiki
 * @subpackage Extensions
 * @author Arent van Korlaar <akvankorlaar 'at' gmail 'dot' com> 
 * @copyright 2015 Arent van Korlaar
 */

class SpecialStylometricAnalysis extends SpecialPage {
  
  public $article_url; 
  
  private $minimum_collections;
  private $minimum_pages_per_collection; 
  private $max_input_textarea; 
  private $user_name;  
  private $full_manuscripts_url; 
  private $collection_array;
  private $collection_hidden_array;
  private $error_message;
  private $manuscripts_namespace_url;
  private $redirect_to_start;
  private $textarea_text; 
   
  //class constructor
  public function __construct(){
    
    global $wgNewManuscriptOptions, $wgArticleUrl;  
    
    $this->article_url = $wgArticleUrl; 
    
    $this->minimum_collections = 2;  //put this into a global variable later on
    $this->minimum_pages_per_collection = 1; //change this to 5 later on and put into a global variable
    $this->max_input_textarea = 500; //the maximum number of input charachters for the textarea 
    $this->error_message = false; //default value
    
    $this->manuscripts_namespace_url = $wgNewManuscriptOptions['manuscripts_namespace'];
    $this->redirect_to_start = false;
    $this->collection_array = array();
    $this->collection_hidden_array = array();
    $this->textarea_text = "";

    parent::__construct('StylometricAnalysis');
	}
  
  /**
   * This function loads requests when a user submits the StylometricAnalysis form
   * 
   * @return boolean
   */
  private function loadRequest(){
    
    $request = $this->getRequest();
        
    //if the request was not posted, return false
    if(!$request->wasPosted()){
      return false;  
    }
    
    $posted_names = $request->getValueNames();    
     
    //identify the button pressed
    foreach($posted_names as $key=>$name){
      
      //remove the numbers from $checkbox to see if it matches to 'collection', 'collection_hidden', or 'redirect_to_start'
      $checkbox_without_numbers = trim(str_replace(range(0,9),'',$name));

      if($checkbox_without_numbers === 'collection'){
        $this->collection_array[$name] = $this->validateInput($request->getText($name));    
      
      }elseif($checkbox_without_numbers === 'collection_hidden'){
        $this->collection_hidden_array[$name] = $this->validateInput(json_decode($request->getText($name)));
        
        //does it also send 'textarea', if it contains no input? 
      }elseif($checkbox_without_numbers === 'textarea'){
        $this->textarea_text = $this->validateInput($request->getText($name));
            
      }elseif($checkbox_without_numbers === 'redirect_to_start'){
        $this->redirect_to_start = true; 
        break;      
      }
    }
    
    if($this->collection_array === false || $this->collection_hidden_array === false || $this->textarea_text === false ){
      return false; 
    }
    
    if($this->redirect_to_start){
      return false; 
    }
        
    return true; 
  }
  
  /**
   * This function validates the textarea input
   * 
   * @param type $input
   */
  private function validateInput($input){
    
    //only allow lowercase letters, uppercase letters, digits, comma's and whitespace 
    if(preg_match('/^[a-zA-Z0-9, ]*$/', $input)){
      return false; 
    }
   
    if(strlen($input) === 0 || strlen($input) > $this->max_input_textarea){
      return false; 
    }
 
    return $input;    
  }
  
  /**
   * This function determines if the user has the right permissions. If a valid request was posted, this request is processed. Otherwise, the default page is shown 
   */
  public function execute(){
    
    $out = $this->getOutput();
    $user_object = $this->getUser();    
    
    if(!in_array('ManuscriptEditors',$user_object->getGroups())){
      return $out->addWikiText('stylometricanalysis-nopermission');
    }
      
    $user_name = $user_object->getName();
    $this->user_name = $user_name; 
    
    $this->full_manuscripts_url = $this->manuscripts_namespace_url . $this->user_name . '/';
    
    $request_was_posted = $this->loadRequest();
    
    if($request_was_posted){
      return $this->processRequest();
    }
    
    return $this->prepareDefaultPage($out);   
  }
  
  /**
   * Processes the request when a user has submitted the form
   * 
   * @return type
   */
  private function processRequest(){
                       
    //next screen should always be a display of your selected texts, the calculated words, and your entered words.
       
    //in this screen enable users to select 3 options: only use your words, only use the calculated words, use both. 
     
    //they can also choose to go back, run a PCA analysis or a clustering analysis
      
    //only after clicking clustering analysis or PCA analysis, the texts should be assembled 
    
//    $texts = $this->constructTexts();
//    
//    //if returned false, one of the posted pages did not exist
//    if(!$texts){
//      return $this->showError('stylometricanalysis-error-notexists');
//    }
    
    return true; 
  }
  
      
  /**
   * This function prepares the default page, in case no request was posted
   * 
   * @return type
   */
  private function prepareDefaultPage($out){
    
    $stylometric_analysis_wrapper = new stylometricAnalysisWrapper($this->user_name);
    
    $collection_urls = $stylometric_analysis_wrapper->checkForManuscriptCollections();
    
    //remove collections with less pages than $this->minimum_pages_per_collection from the list
    foreach($collection_urls as $collection_name => $smaller_url_array){
      if(count($smaller_url_array) < $this->minimum_pages_per_collection){
        unset($collection_urls[$collection_name]);
      }
    }
    
    //check if the total number of collections is less than the minimum
    if(count($collection_urls) < $this->minimum_collections){
      return $out->addWikiText($this->msg('stylometricanalysis-fewcollections'));
    }
   
    return $this->showDefaultPage($collection_urls, $out);    
	}
   
  /**
   * This function constructs the HTML for the default page
   * 
   * @param type $collection_urls
   * @param type $out
   */
  private function showDefaultPage($collection_urls, $out){
    
    $article_url = $this->article_url; 
    
    $out->setPageTitle($this->msg('stylometricanalysis-welcome'));
    
    $about_message = $this->msg('stylometricanalysis-about');
    $version_message = $this->msg('stylometricanalysis-version');  
    $software_message = $this->msg('stylometricanalysis-software');
    $lastedit_message = $this->msg('stylometricanalysis-lastedit');
    
    $html  = "<table id='stylometricanalysis-infobox'>";
    $html .= "<tr><th>$about_message</th></tr>";
    $html .= "<tr><td>$version_message</td></tr>";
    $html .= "<tr><td>$software_message <a href= '' target='_blank'>    </a>.</td></tr>";
    $html .= "<tr><td id='stylometricanalysis-td'><small>$lastedit_message</small></td></tr>";
    $html .= "</table>";
    
    $html .= $this->msg('stylometricanalysis-instruction') . '<br>';
        
    //display the error 
    if($this->error_message){
      
     $error_message = $this->error_message;  
     $html .= "<div class = 'error'>$error_message</div>";
    }
        
    $html .= "<form id='stylometricanalysis-form' action='" . $article_url . "Special:StylometricAnalysis' id='stylometricanalysis-form' method='post'>";
      
    $html .= "<div id='stylometricanalysis-contentwrapper'>";
    $collection_header = $this->msg('stylometricanalysis-collectionheader');

    $html .= "<div id='stylometricanalysis-collection'>";
    $html .= "<h3>$collection_header</h3>";
    $html .= "<ul class ='checkbox_grid'>";

    $a = 0;
    foreach($collection_urls as $collection_name=>$small_url_array){

      //this will be sent when the checkbox is selected
      $json_small_url_array = json_encode($small_url_array['manuscripts_url']);

      //this is to construct the information about the collection which will be displayed to the user
      $manuscript_pages_within_collection = implode(', ',$small_url_array['manuscripts_title']);
      $collection_text = $this->msg('stylometricanalysis-contains') . $manuscript_pages_within_collection . '.';

      //add a checkbox for the collection
      $html .="<li>";
      $html .="<input type='checkbox' name='collection$a' value='$json_small_url_array'>$collection_name";
      $html .="<input type='hidden' name='collection_hidden$a' value='$collection_name'>"; 
      $html .= "<br>";
      $html .= $collection_text; 
      $html .="</li>";
      $html .="<br>";
      $a = ++$a; 
    }
      
    $html .= "</ul>";
    $html .= "</div>";
    
    $word_form_header = $this->msg('stylometricanalysis-wordformheader');
    $placeholder_text = $this->msg('stylometricanalysis-placeholder');
    
    $html .= "<div id='stylometricanalysis-textarea'>";
    $html .= "<h3>$word_form_header</h3>";
      
    $html .= "<br><br>"; 
      
    $html .= "<textarea id='stylometricanalysis-textarea' rows='4' cols = '10' maxlength='500' name='textarea' placeholder='$placeholder_text'>";
    $html .= "</textarea>";
    
    $html .= "</div>";
    
    $html .= "</div>";
      
    $submit_hover_message = $this->msg('stylometricanalysis-hover');
    $submit_message = $this->msg('stylometricanalysis-submit');
    
    $html .= "<input type = 'submit' id='stylometricanalysis-submitbutton' title = $submit_hover_message value=$submit_message>";
    
    $html .= "</form>";
        
    $out->addHTML($html);  
  }
}
