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
class CollateHooks extends ManuscriptDeskBaseHooks {

    /**
     * MediaWikiPerformAction hook. Retrieve the collatex output stored in the database and render the table if the user is in NS_COLLATIONS
     */
    public function onMediaWikiPerformAction(OutputPage $output, Article $article, Title $title, User $user, WebRequest $request, MediaWiki $wiki) {

        try {

            if ($wiki->getAction($request) !== 'view' || !$this->isCollationsNamespace($title)) {
                return true;
            }

            $wrapper = $this->wrapper;
            $partial_url = $title->getPrefixedUrl();
            $this->signature = $wrapper->getSignatureWrapper()->getCollationsSignature($partial_url);

            if (!$this->userIsAllowedToViewThePage($user)) {
                return true;
            }

            $this->user_has_view_permission = true;
            $data = $wrapper->getCollationsData($partial_url);

            $viewer = new CollateViewer($output);
            $viewer->showCollateNamespacePage($data);
        } catch (Exception $e) {
            $this->page_exists = false; 
            return true;
        }

        return true;
    }

    private function isCollationsNamespace($object) {
        $namespace = $this->getNamespaceFromObject($object);

        if ($namespace !== NS_COLLATIONS) {
            return false;
        }

        return true;
    }

    /**
     * PageContentSave hook. Prevent users from making any pages on NS_COLLATIONS, if they are not creating this page
     * through the collation extension
     */
    public function onPageContentSave(&$wikiPage, &$user, &$content, &$summary, $isMinor, $isWatch, $section, &$flags, &$status) {

        try {

            if (!$this->isCollationsNamespace($wikiPage)) {
                return true;
            }

            if (!$this->currentPageExists($wikiPage) && !$this->savePageWasRequested($user)) {
                $status->fatal(new RawMessage($this->getMessage('collatehooks-nopermission')));
                return true;
            }

            return true;
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * AbortMove hook. Prevent users from moving a page on NS_COLLATIONS
     */
    public function onAbortMove(Title $oldTitle, Title $newTitle, User $user, &$error, $reason) {

        if (!$this->isCollationsNamespace($oldTitle)) {
            return true;
        }

        $error = $this->getMessage('collatehooks-move');

        return false;
    }

    /**
     * ArticleDelete hook. Prevent users from deleting collations they have not uploaded
     */
    public function onArticleDelete(WikiPage &$wikipage, User &$user, &$reason, &$error) {

        try {
            $title = $wikipage->getTitle();

            if (!$this->isCollationsNamespace($title)) {
                return true;
            }

            if (!$this->currentUserIsASysop($user)) {
                $error = '<br>' . $this->getMessage('collatehooks-nodeletepermission') . '.';
                return false;
            }

            $deleter = ObjectRegistry::getInstance()->getManuscriptDeskDeleter();
            $deleter->deleteCollationData($title->getPrefixedUrl());
            return true;
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * BeforePageDisplay hook. Load additional modules containing CSS before the page is displayed
     */
    public function onBeforePageDisplay(OutputPage &$out, Skin &$ski) {

        $page_title_with_namespace = $out->getTitle()->getPrefixedURL();

        if ($this->isCollationsNamespace($out) || $page_title_with_namespace === 'Special:Collate') {

            $css_modules = array('ext.collatecss', 'ext.manuscriptdeskbasecss');
            $javascript_modules = array('ext.collatebuttoncontroller', 'ext.javascriptloader');
            $out->addModuleStyles($css_modules);
            $out->addModules($javascript_modules);
        }

        return true;
    }

    /**
     * OutputPageParserOutput hook. Check whether the current user is allowed to view the current collation page  
     */
    public function onOutputPageParserOutput(OutputPage &$out, ParserOutput $parseroutput) {

        if (!$this->isCollationsNamespace($out)) {
            return true;
        }

        if (!$this->user_has_view_permission && $this->page_exists) {
            $parseroutput->setText($this->getMessage('error-viewpermission'));
        }

        return true;
    }

    /**
     * ResourceLoaderGetConfigVars hook. Send configuration variables to javascript. In javascript they are accessed through 'mw.config.get('..') 
     */
    public function onResourceLoaderGetConfigVars(&$vars) {

        global $wgCollationOptions;

        $vars['wgmax_collation_pages'] = $wgCollationOptions['wgmax_collation_pages'];
        $vars['wgmin_collation_pages'] = $wgCollationOptions['wgmin_collation_pages'];

        return true;
    }

}
