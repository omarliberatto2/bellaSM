<?php

namespace DataAccess\Categories;

use BusinessLogic\Categories\Category;
use DataAccess\CommonDao;
use Exception;

class CategoryGateway extends CommonDao {
    /**
     * @param $hesk_settings
     * @return Category[]
     */
    function getAllCategories($hesk_settings) {
        $this->init();

        $sql = 'SELECT * FROM `' . hesk_dbEscape($hesk_settings['db_pfix']) . 'categories`';

        $response = hesk_dbQuery($sql);

        $results = array();
        while ($row = hesk_dbFetchAssoc($response)) {
            $category = new Category();

            $category->id = intval($row['id']);
            $category->name = $row['name'];
            $category->catOrder = intval($row['cat_order']);
            $category->autoAssign = $row['autoassign'] == 1;
            $category->type = intval($row['type']);
            $category->usage = intval($row['usage']);
            $category->backgroundColor = $row['background_color'];
            $category->foregroundColor = $row['foreground_color'];
            $category->displayBorder = $row['display_border_outline'] === '1';
            $category->priority = intval($row['priority']);
            $category->manager = intval($row['manager']) == 0 ? NULL : intval($row['manager']);
            $results[$category->id] = $category;
        }

        $this->close();

        return $results;
    }
}