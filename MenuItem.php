<?php

namespace dokuwiki\plugin\recommend;


/**
 * Menu Item
 */
class MenuItem extends \dokuwiki\Menu\Item\AbstractItem
{
    /** @inheritdoc */
    public function getType()
    {
        return 'recommend';
    }

    /** @inheritdoc */
    public function getSvg()
    {
        return __DIR__ . '/admin.svg';
    }

    /**
     * Get label from plugin language file
     *
     * @return string
     */
    public function getLabel() {
        $hlp = plugin_load('action', 'recommend');
        return $hlp->getLang('menu_recommend');
    }
}
