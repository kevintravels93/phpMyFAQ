<?php
/**
 * $Id$
 *
 * The main Sitemap class
 *
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @since     2007-03-30
 * @copyright (c) 2007 phpMyFAQ Team
 *
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 */

require_once PMF_INCLUDE_DIR . '/Link.php';

class PMF_Sitemap
{
    /**
     * DB handle
     *
     * @var object PMF_Db
     */
    private $db = null;

    /**
     * Language
     *
     * @var string
     */
    private $language = '';

    /**
     * Database type
     *
     * @var string
     */
    private $type = '';

    /**
     * Users
     *
     * @var array
     */
    private $user = array();

    /**
     * Groups
     *
     * @var array
     */
    private $groups = array();

    /**
     * Flag for Group support
     *
     * @var boolean
     */
    private $groupSupport = false;

    /**
     * Constructor
     *
     * @param  PMF_Db  &$db      DB connection
     * @param  string  $language Language
     * @param  integer $user     User
     * @param  array   $groups   Groupss
     * @since  2007-03-30
     * @author Thorsten Rinne <thorsten@phpmyfaq.de>
     */
    function __construct(&$db, $language, $user = null, $groups = null)
    {
        global $DB, $faqconfig;

        $this->db       = &$db;
        $this->language = $language;
        $this->type     = $DB['type'];

        if (is_null($user)) {
            $this->user  = -1;
        } else {
            $this->user  = $user;
        }
        if (is_null($groups)) {
            $this->groups       = array(-1);
        } else {
            $this->groups       = $groups;
        }
        if ($faqconfig->get('main.permLevel') == 'medium') {
            $this->groupSupport = true;
        }
    }

    /**
     * Returns all available first letters
     *
     * @return array
     * @access public
     * @since  2007-03-30
     * @author Thorsten Rinne <thorsten@phpmyfaq.de>
     */
    function getAllFirstLetters()
    {
        global $sids;

        if ($this->groupSupport) {
            $permPart = sprintf("( fdg.group_id IN (%s)
            OR
                (fdu.user_id = %d AND fdg.group_id IN (%s)))",
                implode(', ', $this->groups),
                $this->user,
                implode(', ', $this->groups));
        } else {
            $permPart = sprintf("( fdu.user_id = %d OR fdu.user_id = -1 )",
                $this->user);
        }

        $writeLetters = '<p id="sitemapletters">';

        switch($this->type) {
        case 'db2':
        case 'sqlite':
            $query = sprintf("
                    SELECT
                        DISTINCT substr(fd.thema, 1, 1) AS letters
                    FROM
                        %sfaqdata fd
                    LEFT JOIN
                        %sfaqdata_group AS fdg
                    ON
                        fd.id = fdg.record_id
                    LEFT JOIN
                        %sfaqdata_user AS fdu
                    ON
                        fd.id = fdu.record_id
                    WHERE
                        fd.lang = '%s'
                    AND
                        fd.active = 'yes'
                    AND
                        %s
                    ORDER BY
                        fd.letters",
            SQLPREFIX,
            SQLPREFIX,
            SQLPREFIX,
            $this->language,
            $permPart);
            break;

        default:
            $query = sprintf("
                    SELECT
                        DISTINCT substring(fd.thema, 1, 1) AS letters
                    FROM
                        %sfaqdata fd
                    LEFT JOIN
                        %sfaqdata_group AS fdg
                    ON
                        fd.id = fdg.record_id
                    LEFT JOIN
                        %sfaqdata_user AS fdu
                    ON
                        fd.id = fdu.record_id
                    WHERE
                        fd.lang = '%s'
                    AND
                        fd.active = 'yes'
                    AND
                        %s
                    ORDER BY
                        letters",
            SQLPREFIX,
            SQLPREFIX,
            SQLPREFIX,
            $this->language,
            $permPart);
            break;
        }

        $result = $this->db->query($query);
        while ($row = $this->db->fetch_object($result)) {
            $letters = strtoupper($row->letters);
            if (preg_match("/^[a-z0-9]/i", $letters)) {
                $url = sprintf('%saction=sitemap&amp;letter=%s&amp;lang=%s',
                    $sids,
                    $letters,
                    $this->language);
            $oLink = new PMF_Link(PMF_Link::getSystemRelativeUri().'?'.$url);
            $oLink->text = (string)$letters;
            $writeLetters .= $oLink->toHtmlAnchor().' ';
            }
        }
        $writeLetters .= '</p>';

        return $writeLetters;
    }

    /**
     * Returns all records from the current first letter
     *
     * @param  string $letter Letter
     * @return array
     * @access public
     * @since  2007-03-30
     * @author Thorsten Rinne <thorsten@phpmyfaq.de>
     */
    function getRecordsFromLetter($letter = 'A')
    {
        global $sids, $PMF_LANG;

        if ($this->groupSupport) {
            $permPart = sprintf("( fdg.group_id IN (%s)
            OR
                (fdu.user_id = %d AND fdg.group_id IN (%s)))",
                implode(', ', $this->groups),
                $this->user,
                implode(', ', $this->groups));
        } else {
            $permPart = sprintf("( fdu.user_id = %d OR fdu.user_id = -1 )",
                $this->user);
        }

        $letter = strtoupper($this->db->escape_string(substr($letter, 0, 1)));

        $writeMap = '<ul>';

        switch($this->type) {
            case 'db2':
            case 'sqlite':
                $query = sprintf("
                    SELECT
                        fd.thema AS thema,
                        fd.id AS id,
                        fd.lang AS lang,
                        fcr.category_id AS category_id,
                        fd.content AS snap
                    FROM
                        %sfaqcategoryrelations fcr,
                        %sfaqdata fd
                    LEFT JOIN
                        %sfaqdata_group AS fdg
                    ON
                        fd.id = fdg.record_id
                    LEFT JOIN
                        %sfaqdata_user AS fdu
                    ON
                        fd.id = fdu.record_id
                    WHERE
                        fd.id = fcr.record_id
                    AND
                        substr(fd.thema, 1, 1) = '%s'
                    AND
                        fd.lang = '%s'
                    AND
                        fd.active = 'yes'
                    AND
                        %s",
                    SQLPREFIX,
                    SQLPREFIX,
                    SQLPREFIX,
                    SQLPREFIX,
                    $letter,
                    $this->language,
                    $permPart);
                break;

            default:
                $query = sprintf("
                    SELECT
                        fd.thema AS thema,
                        fd.id AS id,
                        fd.lang AS lang,
                        fcr.category_id AS category_id,
                        fd.content AS snap
                    FROM
                        %sfaqcategoryrelations fcr,
                        %sfaqdata fd
                    LEFT JOIN
                        %sfaqdata_group AS fdg
                    ON
                        fd.id = fdg.record_id
                    LEFT JOIN
                        %sfaqdata_user AS fdu
                    ON
                        fd.id = fdu.record_id
                    WHERE
                        fd.id = fcr.record_id
                    AND
                        substring(fd.thema, 1, 1) = '%s'
                    AND
                        fd.lang = '%s'
                    AND
                        fd.active = 'yes'
                    AND
                        %s",
                    SQLPREFIX,
                    SQLPREFIX,
                    SQLPREFIX,
                    SQLPREFIX,
                    $letter,
                    $this->language,
                    $permPart);
                break;
        }

        $result = $this->db->query($query);
        $oldId = 0;
        while ($row = $this->db->fetch_object($result)) {
            if ($oldId != $row->id) {
                $title = PMF_htmlentities($row->thema, ENT_QUOTES, $PMF_LANG['metaCharset']);
                $url   = sprintf('%saction=artikel&amp;cat=%d&amp;id=%d&amp;artlang=%s',
                    $sids,
                    $row->category_id,
                    $row->id,
                    $row->lang);

                $oLink = new PMF_Link(PMF_Link::getSystemRelativeUri().'?'.$url);
                $oLink->itemTitle = $row->thema;
                $oLink->text = $title;
                $oLink->tooltip = $title;

                $writeMap .= '<li>'.$oLink->toHtmlAnchor().'<br />'."\n";
                $writeMap .= chopString(strip_tags($row->snap), 25). " ...</li>\n";
            }
            $oldId = $row->id;
        }

        $writeMap .= '</ul>';

        return $writeMap;
    }
}