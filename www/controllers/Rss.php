<?PHP

namespace controllers;

/**
 * Controller for rss access
 *
 * @package    controllers
 * @copyright  Copyright (c) Tobias Zeising (http://www.aditu.de)
 * @license    GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 * @author     Tobias Zeising <tobias.zeising@aditu.de>
 */
class Rss extends BaseController {
	
    /**
     * rss feed
     *
     * @return void
     */
    public function rss() {
        $feedWriter = new \FeedWriter(\RSS2);
        $feedWriter->setTitle(\F3::get('rss_title'));
        
        $feedWriter->setLink($this->view->base);
        
        // set options
        $options = array();
        if(count($_GET)>0)
            $options = $_GET;
        $options['items'] = \F3::get('rss_max_items');
        
        // get items
        $newestEntryDate = false;
        $lastid = -1;
        $itemDao = new \daos\Items();
        foreach($itemDao->get($options) as $item) {
            if($newestEntryDate===false)
                $newestEntryDate = $item['datetime'];
            $newItem = $feedWriter->createNewItem();
            $newItem->setTitle(str_replace('&', '&amp;', html_entity_decode(utf8_decode($item['title']))));
            @$newItem->setLink($item['link']);
            $newItem->setDate($item['datetime']);
            $newItem->setDescription(str_replace('&#34;', '"', $item['content']));
            $feedWriter->addItem($newItem);
            $lastid = $item['id'];
        }
        
        if($newestEntryDate===false)
            $newestEntryDate = date(\DATE_ATOM , time());
        $feedWriter->setChannelElement('updated', $newestEntryDate);
        
        // mark as read
        if(\F3::get('rss_mark_as_read')==1 && $lastid!=-1)
            $itemDao->mark($lastid);
        
        $feedWriter->genarateFeed();
    }
	
}
