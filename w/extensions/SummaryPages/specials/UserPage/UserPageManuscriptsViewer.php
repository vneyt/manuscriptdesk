<?php

/**
 * This file is part of the newManuscript extension
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
class UserPageManuscriptsViewer implements UserPageViewerInterface{

    use HTMLUserPageMenuBar,
        HTMLJavascriptLoaderDots,
        HTMLPreviousNextPageLinks;

    private $out;
    private $user_name;

    public function __construct(OutputPage $out, $user_name) {
        $this->out = $out;
        $this->user_name = $user_name;
    }

    public function showPage($button_name, $page_titles, $offset, $next_offset) {

        global $wgArticleUrl;
        $article_url = $wgArticleUrl;
        $out = $this->out;
        $user_name = $this->user_name;

        $out->setPageTitle($out->msg('userpage-welcome') . ' ' . $user_name);
        $edit_token = $out->getUser()->getEditToken();

        $html = "";
        $html .= $this->getHTMLUserPageMenuBar($out, $edit_token, array('button-active', 'button', 'button'));
        $html .= $this->getHTMLJavascriptLoaderDots();
        $html .= "<div class='javascripthide'>";
        $html .= $this->getHTMLPreviousNextPageLinks($out, $edit_token, $offset, $next_offset, $button_name, 'UserPage');

        $created_message = $out->msg('userpage-created');
        $html .= "<br>";

        $html .= "<p>" . $out->msg('userpage-manuscriptinstr') . "</p>";
        $html .= "<table id='userpage-table' style='width: 100%;'>";
        $html .= "<tr>";
        $html .= "<td class='td-long'><b>" . $out->msg('userpage-tabletitle') . "</b></td>";
        $html .= "<td><b>" . $out->msg('userpage-creationdate') . "</b></td>";
        $html .= "</tr>";

        foreach ($page_titles as $single_page_data) {

            $title = isset($single_page_data['manuscripts_title']) ? $single_page_data['manuscripts_title'] : '';
            $url = isset($single_page_data['manuscripts_url']) ? $single_page_data['manuscripts_url'] : '';
            $date = $single_page_data['manuscripts_date'] !== '' ? $single_page_data['manuscripts_date'] : 'unknown';

            $html .= "<tr>";
            $html .= "<td class='td-long'><a href='" . $article_url . htmlspecialchars($url) . "' title='" . htmlspecialchars($title) . "'>" .
                htmlspecialchars($title) . "</a></td>";
            $html .= "<td>" . htmlspecialchars($date) . "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";
        $html .= "</div>";

        return $out->addHTML($html);
    }

    public function showEmptyPageTitlesError($button_name) {

        global $wgArticleUrl;
        $out = $this->out;
        $user_name = $this->user_name;

        $out->setPageTitle($out->msg('userpage-welcome') . ' ' . $user_name);
        
        $edit_token = $out->getUser()->getEditToken();

        $html = "";
        $html .= $this->getHTMLUserPageMenuBar($out, $edit_token, array('button-active', 'button', 'button'));
        $html .= $this->getHTMLJavascriptLoaderDots();
        $html .= "<div class='javascripthide'>";
        $html .= "<p>" . $out->msg('userpage-nomanuscripts') . "</p>";
        $html .= "<p><a class='userpage-transparent' href='" . $wgArticleUrl . "Special:NewManuscript'>" . $out->msg('userpage-newmanuscriptpage') . "</a></p>";
        $html .= "</div>";

        return $out->addHTML($html);
    }

}