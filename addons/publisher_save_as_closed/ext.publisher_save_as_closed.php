<?php use BoldMinded\Publisher\Enum\Status;
use BoldMinded\Publisher\Service\Cache\RequestCache;
use BoldMinded\Publisher\Service\Request;
use BoldMinded\Reel\Service\Helper;
use EllisLab\ExpressionEngine\Model\Channel\ChannelEntry;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package     ExpressionEngine
 * @subpackage  Extensions
 * @category    Publisher - Save as Closed
 * @author      Brian Litzinger
 * @copyright   Copyright (c) 2018 - Brian Litzinger
 * @link        http://boldminded.com/add-ons/publisher
 * @license
 *
 * Copyright (c) 2011, 2012. BoldMinded, LLC
 * All rights reserved.
 *
 * This source is commercial software. Use of this software requires a
 * site license for each domain it is used on. Use of this software or any
 * of its source code without express written permission in the form of
 * a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 * As part of the license agreement for this software, all modifications
 * to this source must be submitted to the original author for review and
 * possible inclusion in future releases. No compensation will be provided
 * for patches, although where possible we will attribute each contribution
 * in file revision notes. Submitting such modifications constitutes
 * assignment of copyright to the original author (Brian Litzinger and
 * BoldMinded, LLC) for such modifications. If you do not wish to assign
 * copyright to the original author, your license to  use and modify this
 * source is null and void. Use of this software constitutes your agreement
 * to this clause.
 */

class Publisher_save_as_closed_ext
{
    const CLOSED_STATUS_KEY = 'closed';

    public $settings = [];
    public $required_by = [];
    public $name;
    public $version;
    public $description;
    public $settings_exist;
    public $docs_url;

    public function __construct($settings = '') {
        $extConfig = include PATH_THIRD . 'publisher_save_as_closed/addon.setup.php';

        $this->name = $extConfig['name'];
        $this->version = $extConfig['version'];
        $this->description = $extConfig['description'];
        $this->settings_exist = $extConfig['settings_exist'];
        $this->docs_url = $extConfig['docs_url'];
    }

    /**
     * @param $saveOptions
     * @return mixed
     */
    public function publisher_toolbar_status($saveOptions) {
        $saveOptions[self::CLOSED_STATUS_KEY] = lang(self::CLOSED_STATUS_KEY);
        return $saveOptions;
    }

    /**
     * @param ChannelEntry $channelEntry
     * @param $meta
     */
    public function before_channel_entry_save(ChannelEntry $channelEntry, $meta)
    {
        /** @var Request $requestService */
        $requestService = ee(Request::NAME);
        $saveStatus = $requestService->getSaveStatus();

        /** @var RequestCache $requestCacheService */
        $requestCacheService = ee(RequestCache::NAME);

        // Force Publisher to save everything as a Draft since it does not recognize closed as a status
        if ($saveStatus === self::CLOSED_STATUS_KEY) {
            $_POST['publisher_save_status'] = Status::DRAFT;
            $requestService->setSaveStatus(Status::DRAFT);
            $requestCacheService->set('publisher_save_as_closed', true);
        }
    }

    /**
     * @param $entryId
     * @param $meta
     * @param $entryData
     * @return array
     */
    public function publisher_entry_save_start($entryId, $meta, $entryData) {
        /** @var RequestCache $requestCacheService */
        $requestCacheService = ee(RequestCache::NAME);
        $saveAsClosed = $requestCacheService->get('publisher_save_as_closed');

        if ($saveAsClosed) {
            ee('db')
                ->where('entry_id', $entryId)
                ->update('channel_titles', array(
                    'status' => self::CLOSED_STATUS_KEY,
                ));
        }

        // Return unmodified data
        return [
            $meta,
            $entryData
        ];
    }

    public function activate_extension() {
        $this->addHooks([
            ['hook'=>'before_channel_entry_save', 'method'=>'before_channel_entry_save', 'priority'=>1],
            ['hook'=>'publisher_toolbar_status', 'method'=>'publisher_toolbar_status'],
            ['hook'=>'publisher_entry_save_start', 'method'=>'publisher_entry_save_start'],
        ]);
    }

    public function update_extension($current = '') {}

    public function disable_extension() {
        ee('db')
            ->where('class', __CLASS__)
            ->delete('extensions');
    }

    private function addHooks($hooks = [])
    {
        if (empty($hooks)) {
            return;
        }

        $extConfig = include PATH_THIRD . 'publisher_save_as_closed/addon.setup.php';

        foreach($hooks as $hook) {
            $hook = array_merge([
                'class' => __CLASS__,
                'settings' => '',
                'priority' => 5,
                'version' => $extConfig['version'],
                'enabled' => 'y',
            ], $hook);

            /** @var \CI_DB_result $query */
            $query = ee('db')->get_where('extensions', array(
                'hook' => $hook['hook'],
                'class' => $hook['class']
            ));

            if($query->num_rows() == 0) {
                ee('db')->insert('extensions', $hook);
            }
        }
    }
}
