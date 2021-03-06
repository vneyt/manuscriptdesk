<?php

/**
 * This file is part of the Manuscript Desk (github.com/akvankorlaar/manuscriptdesk)
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
class SpecialStylometricAnalysis extends ManuscriptDeskBaseSpecials {

    private $python_path;
    private $form_type;
    private $base_outputpath;
    private $base_linkpath;
    private $min_words_collection;  //min words that should be in a collection. This is checked using str_word_count, but it has to be checked if str_word_count equals the number of tokens.
    private $collection_data;
    private $collection_name_data;
    private $pystyl_config;
    private $pystyl_path;

    //PyStyl $config_array information: 
    //removenonalpha : wheter or not to keep alphabetical symbols
    //lowercase : wheter or not to lowercase all charachters
    //tokenizer : str, default=None select the `nltk` tokenizer to be used. Currentlysupports: 'whitespace' (split on whitespace) and 'words' (alphabetic series of characters)
    //minimumsize : minimum size of texts (in tokens), to be included in the set of tokenized texts
    //maximumsize : maximum size of texts (in tokens). Longer texts will be truncated to max_size after tokenization
    //segmentsize : segment_size : int, default=0 The size of the segments to be extracted (in tokens). If `segment_size`=0, no segmentation will be applied to the tokenized texts
    //stepsize : The nb of words in between two consecutive segments (in tokens). If `step_size`=zero, non-overlapping segments will be created. Else, segments will partially overlap
    //removepronouns : Whether to remove personal pronouns. If the `corpus.language` is supported, we will load the relevant list from under `pystyl/pronouns`. The pronoun lists are identical to those for 'Stylometry with R'
    //vectorspace : Which vector space to use. Must be one of: 'tf', 'tf_scaled', 'tf_std', 'tf_idf', 'bin'
    //featuretype
    //ngramsize : The length of the ngrams to be extracted
    //mfi : The nb of most frequent items (words or ngrams) to extract
    //minimumdf : Proportion of documents in which a feature should minimally occur. Useful to ignore low-frequency features
    //maximumdf : Proportion of documents in which a feature should maximally occur. Useful for 'culling' and ignoring features which don't appear in enough texts
    //visualization1: The chosen visualization. This could be for example a dendrogram
    //visualization2: THe second chosen visualization

    public function __construct() {
        parent::__construct('StylometricAnalysis');
    }

    /**
     * Set variables that are used throughout the special page 
     */
    protected function setVariables() {
        global $wgStylometricAnalysisOptions, $wgWebsiteRoot, $wgPythonPath, $wgPystylPath;
        parent::setVariables();

        $this->python_path = $wgPythonPath;
        $this->min_words_collection = $wgStylometricAnalysisOptions['min_words_collection'];
        $svg_dir = 'stylometricanalysissvg';
        $this->base_outputpath = $wgWebsiteRoot . '/' . $svg_dir . '/' . $this->user_name;
        $this->base_linkpath = $svg_dir . '/' . $this->user_name;
        $this->pystyl_path = $wgPystylPath;
        return true;
    }

    /**
     * Process all requests
     */
    protected function processRequest() {

        $request_processor = $this->request_processor;
        $request_processor->checkEditToken($this->getUser());

        if ($request_processor->defaultPageWasPosted()) {
            $this->processDefaultPage();
            return true;
        }

        if ($request_processor->form2WasPosted()) {
            $this->processForm2();
            return true;
        }

        if ($request_processor->savePagePosted()) {
            $this->processSavePageRequest();
            return true;
        }

        if ($request_processor->redirectBackPosted()) {
            $this->getDefaultPage();
            return true;
        }

        throw new Exception('error-request');
    }

    protected function getDefaultPage($error_message = '') {
        $user_collection_data = $this->getUserCollectionData();
        return $this->viewer->showDefaultPage($error_message, $user_collection_data);
    }

    /**
     * Process a user submission of the default page 
     */
    private function processDefaultPage() {
        $this->form_type = 'Form1';
        $this->collection_data = $this->request_processor->getDefaultPageData();
        $this->collection_name_data = $this->constructCollectionNameData();
        return $this->viewer->showForm2($this->collection_data, $this->collection_name_data, $this->getContext());
    }

    /**
     * Prepare and handle the actual Pystyl analysis after the user has submitted the second form
     */
    private function processForm2() {
        $request_processor = $this->request_processor;
        $this->form_type = 'Form2';
        $this->collection_data = $request_processor->getForm2CollectionData();
        $this->collection_name_data = $this->constructCollectionNameData();
        $this->pystyl_config = $request_processor->getForm2PystylConfigurationData();

        $texts = $this->getPageTextsForCollections();

        list($output_file_name1, $output_file_name2) = $this->constructPystylOutputFileNames();
        list($full_outputpath1, $full_outputpath2) = $this->constructFullOutputPathOfPystylOutputImages($output_file_name1, $output_file_name2);
        list($full_linkpath1, $full_linkpath2) = $this->constructFullLinkPathOfPystylOutputImages($output_file_name1, $output_file_name2);

        $this->setAdditionalPystylConfigValues($texts, $full_outputpath1, $full_outputpath2);
        $full_textfilepath = $this->constructFullTextfilePath();
        $this->insertPystylConfigIntoTextfile($full_textfilepath);
        $command = $this->constructShellCommandToCallPystyl();
        $pystyl_output = $this->callPystyl($command, $full_textfilepath);
        $this->deleteTemporaryTextfile($full_textfilepath);
        $this->checkPystylOutput($pystyl_output, $full_outputpath1, $full_outputpath2);

        $time = idate('U'); //time format integer (Unix Timestamp). This timestamp is used to see how old values are
        $this->updateDatabase($time, $full_linkpath1, $full_linkpath2, $full_outputpath1, $full_outputpath2);

        return $this->viewer->showResult($this->pystyl_config, $this->collection_name_data, $full_linkpath1, $full_linkpath2, $time);
    }

    /**
     * Process the request when the user wants to save the current analysis 
     */
    private function processSavePageRequest() {
        $time_identifier = $this->request_processor->getSavePageData();
        $wrapper = $this->wrapper;
        $wrapper->transferDataFromTempStylometricAnalysisToStylometricAnalysisTable($time_identifier);
        list($new_page_url, $main_title_lowercase) = $wrapper->getStylometricAnalysisNewPageData($time_identifier);
        $wrapper->getAlphabetNumbersWrapper()->modifyAlphabetNumbersSingleValue($main_title_lowercase, 'AllStylometricAnalysis', 'add');
        $local_url = $this->createNewWikiPage($new_page_url);
        return $this->getOutput()->redirect($local_url);
    }

    /**
     * Create a URL that can be used to save the current analysis 
     */
    private function createNewPageUrl($main_title) {
        $user_name = $this->user_name;
        $year_month_day = date('Ymd');
        $hours_minutes_seconds = date('his');
        return 'Stylometricanalysis:' . $user_name . "/" . $main_title . "/" . $year_month_day . "/" . $hours_minutes_seconds;
    }

    /**
     * Fill an array with the collection names of the current analysis 
     */
    private function constructCollectionNameData() {
        $collection_name_data = array();
        foreach ($this->collection_data as $index => $single_collection_data) {
            $collection_name_data[] = $single_collection_data['collection_name'];
        }
        return $collection_name_data;
    }

    /**
     * Get the page texts for the selected collections 
     */
    private function getPageTextsForCollections() {
        $texts = array();
        $a = 1;
        foreach ($this->collection_data as $single_collection_data) {
            $text_processor = ObjectRegistry::getInstance()->getManuscriptDeskBaseTextProcessor();
            $all_texts_for_one_collection = $text_processor->getAllTextsForOneCollection($single_collection_data);
            $this->checkForStylometricAnalysisCollectionErrors($all_texts_for_one_collection);
            $collection_name = isset($single_collection_data['collection_name']) ? $single_collection_data['collection_name'] : 'collection' . $a;

            //add the combined texts of one collection to $texts
            $texts["collection" . $a] = array(
              "title" => "$collection_name",
              "target_name" => "$collection_name",
              "text" => "$all_texts_for_one_collection",
            );

            $a += 1;
        }

        return $texts;
    }

    /**
     * Check if a collection is ok for analysis
     */
    private function checkForStylometricAnalysisCollectionErrors($all_texts_for_one_collection) {
        $collection_n_words = str_word_count($all_texts_for_one_collection);
        $pystyl_config = $this->pystyl_config;

        if ($collection_n_words < $this->min_words_collection) {
            $this->form_type = 'Form1';
            throw new Exception('stylometricanalysis-error-toosmall');
        }

        if ($collection_n_words < $pystyl_config['minimumsize']) {
            throw new Exception('stylometricanalysis-error-minsize');
        }

        if ($collection_n_words < ($pystyl_config['segmentsize'] + $pystyl_config['stepsize'])) {
            throw new Exception('stylometricanalysis-error-segmentsize');
        }

        if ($collection_n_words < $pystyl_config['ngramsize']) {
            throw new Exception('stylometricanalysis-error-ngramsize');
        }

        return true;
    }

    /**
     * Construct the output file names of the images 
     */
    private function constructPystylOutputFileNames() {
        $imploded_collection_name_data = implode('', $this->collection_name_data);
        $year_month_day = date('Ymd');
        $hours_minutes_seconds = date('his');

        $file_name1 = $imploded_collection_name_data . $year_month_day . $hours_minutes_seconds . '.svg';
        $file_name2 = $imploded_collection_name_data . $year_month_day . $hours_minutes_seconds . 2 . '.svg';

        return array($file_name1, $file_name2);
    }

    /**
     * Construct the output paths of the images 
     */
    private function constructFullOutputPathOfPystylOutputImages($file_name1, $file_name2) {
        $full_outputpath1 = $this->base_outputpath . '/' . $file_name1;
        $full_outputpath2 = $this->base_outputpath . '/' . $file_name2;

        if (is_file($full_outputpath1) || is_file($full_outputpath2)) {
            throw new Exception('stylometricanalysis-error-internal');
        }

        return array($full_outputpath1, $full_outputpath2);
    }

    /**
     * Construct the link path for the outputimages, which will be placed in the HTML 
     */
    private function constructFullLinkPathOfPystylOutputImages($file_name1, $file_name2) {
        $full_linkpath1 = '/' . $this->base_linkpath . '/' . $file_name1;
        $full_linkpath2 = '/' . $this->base_linkpath . '/' . $file_name2;
        return array($full_linkpath1, $full_linkpath2);
    }

    /**
     * Construct the command to call PyStyl 
     */
    private function constructShellCommandToCallPystyl() {
        $python_path = $this->python_path;
        $pystyl_path = $this->pystyl_path;
        $pystyl_analysis_file = $pystyl_path . '/' . 'manuscriptdeskanalysis.py';
        return $python_path . ' -W ignore ' . $pystyl_analysis_file;
    }

    /**
     * Construct the config array that will be sent to Pystyl
     */
    private function setAdditionalPystylConfigValues(array $texts, $full_outputpath1, $full_outputpath2) {
        $this->pystyl_config['texts'] = $texts;
        $this->pystyl_config['full_outputpath1'] = $full_outputpath1;
        $this->pystyl_config['full_outputpath2'] = $full_outputpath2;
        $this->pystyl_config['base_outputpath'] = $this->base_outputpath;
        return true;
    }

    /**
     * Construct a temporary textfile in which the data for the analysis will be placed later on. Initially, this was done through the command line,
     * but due to some instabilities, this approach is chosen. 
     */
    private function constructFullTextfilePath() {
        return $this->base_outputpath . '/' . 'temptextfile.txt';
    }

    /**
     * Insert data into the temporary textfile with config data used by PyStyl
     */
    private function insertPystylConfigIntoTextfile($full_textfilepath) {

        if (is_file($full_textfilepath)) {
            unlink($full_textfilepath);
        }

        if (!is_dir($this->base_outputpath)) {
            mkdir($this->base_outputpath, 0755, true);
        }

        $textfile = fopen($full_textfilepath, 'w');
        fwrite($textfile, json_encode($this->pystyl_config));
        fclose($textfile);

        if (!is_file($full_textfilepath)) {
            throw new Exception('stylometricanalysis-error-internal');
        }

        return true;
    }

    /**
     * Delete the temporary textfile with PyStyl configuration information 
     */
    private function deleteTemporaryTextfile($full_textfilepath) {

        if (!is_file($full_textfilepath)) {
            return true;
        }

        unlink($full_textfilepath);

        if (is_file($full_textfilepath)) {
            throw new Exception('stylometricanalysis-error-internal');
        }
    }

    /**
     * This function calls PyStyl through the command line. Format: python path' -W ignore /path/to/analysis/file.py "'/path/to/textfile.txt"' 
     */
    private function callPystyl($command, $full_textfilepath) {
        $full_textfilepath = "\"'$full_textfilepath'\"";
        $full_textfilepath = str_replace('\\', '', $full_textfilepath);
        $full_command = $command . ' ' . $full_textfilepath;      
        return shell_exec($full_command);
    }

    /**
     * Check the output of PyStyl and throw an exception if needed 
     */
    private function checkPystylOutput($pystyl_output, $full_outputpath1, $full_outputpath2) {

        //something went wrong when importing data into PyStyl
        if (strpos($pystyl_output, 'stylometricanalysis-error-import') !== false) {
            throw new Exception('stylometricanalysis-error-import');
        }

        //the path already exists
        if (strpos($pystyl_output, 'stylometricanalysis-error-path') !== false) {
            throw new Exception('stylometricanalysis-error-path');
        }

        //something went wrong when doing the analysis in PyStyl
        if (strpos($pystyl_output, 'stylometricanalysis-error-analysis') !== false) {
            throw new Exception('stylometricanalysis-error-analysis');
        }

        if (!is_file($full_outputpath1) || !is_file($full_outputpath2)) {
            throw new Exception('stylometricanalysis-error-internal');
        }

        return true;
    }

    /**
     * Update the database after the analysis 
     */
    private function updateDatabase($time = 0, $full_linkpath1, $full_linkpath2, $full_outputpath1, $full_outputpath2) {
        $main_title = implode('', $this->collection_name_data);
        $new_page_url = $this->createNewPageUrl($main_title);
        $date = date("d-m-Y H:i:s");
        $wrapper = $this->wrapper;
        $wrapper->clearOldPystylOutput($time);
        $wrapper->storeTempStylometricAnalysis($this->collection_name_data, $time, $new_page_url, $date, $full_linkpath1, $full_linkpath2, $full_outputpath1, $full_outputpath2, $this->pystyl_config, $main_title);
        return true;
    }

    /**
     * Get collection data for the current user 
     */
    private function getUserCollectionData() {
        $wrapper = $this->wrapper;
        return $wrapper->getManuscriptsCollectionData();
    }

    /**
     * Handle exceptions, and either redirect to the default page, or to form 2 
     */
    protected function handleExceptions(Exception $exception_error) {
        $this->setViewer();
        $viewer = $this->viewer;
        $error_identifier = $exception_error->getMessage();
        $error_message = $this->constructErrorMessage($exception_error, $error_identifier);

        if ($error_identifier === 'error-nopermission') {
            return $viewer->showSimpleErrorMessage($error_message);
        }

        if ($error_identifier === 'error-fewuploads') {
            return $viewer->showFewUploadsError($error_identifier);
        }

        if ($this->form_type === 'Form2' && isset($this->collection_data) && isset($this->collection_name_data)) {
            return $viewer->showForm2($this->collection_data, $this->collection_name_data, $this->getContext(), $error_message);
        }

        return $this->getDefaultPage($error_message);
    }

    public function setViewer() {

        if (isset($this->viewer)) {
            return;
        }

        return $this->viewer = ObjectRegistry::getInstance()->getStylometricAnalysisViewer($this->getOutput());
    }

    public function setWrapper() {

        if (isset($this->wrapper)) {
            return;
        }

        $wrapper = ObjectRegistry::getInstance()->getStylometricAnalysisWrapper();
        $wrapper->setUserName($this->user_name);
        return $this->wrapper = $wrapper;
    }

    public function setRequestProcessor() {

        if (isset($this->request_processor)) {
            return;
        }
        return $this->request_processor = ObjectRegistry::getInstance()->getStylometricAnalysisRequestProcessor($this->getRequest());
    }

    /**
     * Callback function. Makes sure the page is redisplayed in case there was an error in Form 2 
     */
    static function callbackForm2($form_data) {
        return false;
    }

}
