<?php

use App\Config\Services;

/**
 * @package FusionCMS
 * @link    https://github.com/FusionWowCMS/FusionCMS
 */

class Cms_model extends CI_Model
{
    private $db;
    /**
     * Connect to the database
     */
    public function __construct()
    {
        parent::__construct();

        $this->db = $this->load->database("cms", true);

        $this->logVisit();

        if ($this->config->item('detect_language')) {
            $this->setLangugage();
        }
    }

    private function logVisit()
    {
        if (!$this->input->is_ajax_request() && !isset($_GET['is_json_ajax'])) {
            $data = [
                'date'      => date("Y-m-d"),
                'ip'        => $this->input->ip_address(),
                'timestamp' => time()
            ];

            $this->db->on_duplicate('visitor_log', $data);
        }
    }

    public function getSideboxes(string $type = 'side', string $page = '*')
    {
        // Query: Prepare
        $query = $this->db->from('sideboxes')
                          ->select('*')
                          ->order_by('order', 'ASC');

        // Query: Filter (Type)
        if($type && in_array($type, ['top', 'side', 'bottom']))
            $query = $query->where('location', $type);

        // Query: Filter (Page)
        if($page && $page !== '*')
            $query = $query->group_start()
                           ->like('pages', str_replace(':page', $page, '":page"'), 'both')
                           ->or_like('pages', '"*"', 'both')
                           ->group_end();

        // Query: Execute
        $query = $query->get();

        // Query: Make sure we have results
        if($query->num_rows())
            return $query->result_array();

        return [];
    }

    /**
     * Load the slider images
     *
     * @return array|null
     */
    public function getSlides(): ?array
    {
        $query = $this->db->query("SELECT * FROM image_slider ORDER BY `order` ASC");

        if ($query->num_rows() > 0) {
            return $query->result_array();
        }

        return null;
    }

    /**
     * Get the links of one direction
     *
     * @param string $type ID of the specific menu
     * @return array|null
     */
    public function getLinks(string $type = "top"): ?array
    {
        if (in_array($type, array("top", "side", "bottom"))) {
            $query = $this->db->query("SELECT * FROM menu WHERE type = ? ORDER BY `parent_id` ASC, `order` ASC", [$type]);
        } else {
            $query = $this->db->query("SELECT * FROM menu ORDER BY `order` ASC");
        }

        if ($query->num_rows() > 0) {
            return $query->result_array();
        }

        return null;
    }

    /**
     * Get the selected page from the database
     *
     * @param string $page
     * @return array|null
     */
    public function getPage(string $page): ?array
    {
        $this->db->select('*')->from('pages')->where('identifier', $page);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            return $result[0];
        }

        return null;
    }

    /**
     * Get any old rank ID (to avoid foreign key errors)
     *
     * @return bool|int
     */
    public function getAnyOldRank(): bool|int
    {
        $query = $this->db->query("SELECT id FROM `ranks` ORDER BY id ASC LIMIT 1");

        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            return $result[0]['id'];
        }

        return false;
    }

    /**
     * Get all pages
     *
     * @return array|null
     */
    public function getPages(): ?array
    {
        $this->db->select('*')->from('pages');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return $query->result_array();
        }

        return null;
    }

    /**
     * Get all data from the realms table
     *
     * @return array|null
     */
    public function getRealms(): ?array
    {
        $this->db->select('*')->from('realms');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return $query->result_array();
        }

        return null;
    }

    /**
     * Get the realm database information
     *
     * @param Int $id
     * @return array|null
     */
    public function getRealm(int $id): ?array
    {
        $this->db->select('*')->from('realms')->where('id', $id);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            return $result[0];
        }

        return null;
    }

    public function getBackups($id = false)
    {
        if ($id) {
            $query = $this->db->query("SELECT backup_name FROM backup where id = ?", [$id]);

            if ($query->num_rows() > 0) {
                $result = $query->result_array();
                return $result[0]['backup_name'];
            } else {
                return false;
            }
        } else {
            $query = $this->db->query("SELECT * FROM backup ORDER BY `id` ASC");

            if ($query->num_rows() > 0) {
                return $query->result_array();
            } else {
                return false;
            }
        }
    }

    public function getBackupCount()
    {
        $this->db->select("COUNT(id) 'count'");
        $query = $this->db->get('backup');

        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            return $result[0]['count'];
        }

        return null;
    }

    public function deleteBackups($id)
    {
        $this->db->query("delete FROM backup WHERE id = ?", [$id]);
    }

    public function getTemplate($id)
    {
        $query = $this->db->query("SELECT * FROM email_templates WHERE id= ? LIMIT 1", [$id]);

        if ($query->num_rows() > 0) {
            $row = $query->result_array();

            return $row[0];
        } else {
            return false;
        }
    }

    public function getNotifications($id, $count = false)
    {
        if ($count) {
            $this->db->select('*');
            $this->db->where('uid', $id);
            $this->db->where('read', 0);
            return $this->db->count_all_results('notifications');
        } else {
            $this->db->select('*')->from('notifications')->where('uid', $id);
            $query = $this->db->get();

            if ($query->num_rows() > 0) {
                return $query->result_array();
            }
        }

        return null;
    }

    public function setReadNotification($id, $uid, $all = false)
    {
        $this->db->set('read', 1);
        if (!$all) {
            $this->db->where('id', $id);
        }
        $this->db->where('uid', $uid);
        $this->db->update('notifications');
    }

    private function setLangugage()
    {
        $langs = $this->agent->languages();

        foreach ($langs as $lang) {
            // Check if its in the array
            if (in_array($lang, array_keys($this->config->item('supported_languages')))) {
                $setLang = $this->config->item('supported_languages')[$lang]['name'];
                break;
            }
        }

        // If no language has been worked out - or it is not supported - use the default
        if (!in_array($lang, array_keys($this->config->item('supported_languages')))) {
            $setLang = $this->config->item('default_language');
        }

        if (Services::session()->get('online')) {
            $this->user->setLanguage($setLang);
        } else {
            Services::session()->set(['language' => $setLang]);
        }
    }

    private function getSession($session)
    {
        $this->db->where('ip_address', $session['ip_address']);
        $this->db->where('user_agent', $session['user_agent']);
        $query = $this->db->get("ci_sessions");

        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return false;
        }
    }

    public function getMessagesCount(): int
    {
        return 0;
    }
}
