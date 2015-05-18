/**
 * base javascript application
 *
 * @package    public_js
 * @copyright  Copyright (c) Tobias Zeising (http://www.aditu.de)
 * @license    GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 */
var selfoss = {

    /**
     * current filter settings
     * @var mixed
     */
    filter: {
        offset: 0,
        itemsPerPage: 0,
        search: '',
        type: 'newest',
        tag: '',
        source: '',
        sourcesNav: false,
        ajax: true
    },

    /**
     * instance of the currently running XHR that is used to reload the items list
     */
    activeAjaxReq: null,
    
    /**
     * last stats update
     */
    lastStatsUpdate: Date.now(),


    /**
     * initialize application
     */
    init: function() {
        jQuery(document).ready(function() {
            // reduced init on login
            if($('#login').length>0) {
                $('#username').focus();
                return;
            }
        
            // set items per page
            selfoss.filter.itemsPerPage = $('#config').data('items_perpage');
            
            // initialize type by homepage config param
            selfoss.filter.type = $('#nav-filter li.active').attr('id').replace('nav-filter-', '');
            
            // init shares
            selfoss.shares.init();

            // init events
            selfoss.events.init();
            
            // init shortcut handler
            selfoss.shortcuts.init();

            // setup periodic stats reloader
            window.setInterval(selfoss.reloadStats, 60*1000);
        });
    },
    
    
    /**
     * returns an array of name value pairs of all form elements in given element
     *
     * @return void
     * @param element containing the form elements
     */
    getValues: function(element) {
        var values = {};
        
        $(element).find(':input').each(function (i, el) {
            // get only input elements with name
            if($.trim($(el).attr('name')).length!=0) {
                values[$(el).attr('name')] = $(el).val();
                if($(el).attr('type')=='checkbox')
                    values[$(el).attr('name')] = $(el).attr('checked') ? 1 : 0;
            }
        });
        
        return values;
    },
    
    
    /**
     * insert error messages in form
     *
     * @return void
     * @param form target where input fields in
     * @param errors an array with all error messages
     */
    showErrors: function(form, errors) {
        $(form).find('span.error').remove();
        $.each(errors, function(key, val) {
            form.find("[name='"+key+"']").addClass('error').parent('li').append('<span class="error">'+val+'</span>');
        });
    },
    
    
    /**
     * indicates whether a mobile device is host
     *
     * @return true if device resolution smaller equals 1024
     */
    isMobile: function() {
        // first check useragent
        if((/iPhone|iPod|iPad|Android|BlackBerry/).test(navigator.userAgent))
            return true;
        
        // otherwise check resolution
        return selfoss.isTablet() || selfoss.isSmartphone();
    },
    
    
    /**
     * indicates whether a tablet is the device or not
     *
     * @return true if device resolution smaller equals 1024
     */
    isTablet: function() {
        if($(window).width()<=1024)
            return true;
        return false;
    },

    
    /**
     * indicates whether a tablet is the device or not
     *
     * @return true if device resolution smaller equals 1024
     */
    isSmartphone: function() {
        if($(window).width()<=640)
            return true;
        return false;
    },
    
    
    /**
     * refresh current items.
     *
     * @return void
     */
    reloadList: function() {
        if (selfoss.activeAjaxReq !== null)
            selfoss.activeAjaxReq.abort();

        if (location.hash == "#sources") {
            location.hash = "";
            return;
        }

        $('#content').addClass('loading').html("");

        selfoss.activeAjaxReq = $.ajax({
            url: $('base').attr('href'),
            type: 'GET',
            dataType: 'json',
            data: selfoss.filter,
            success: function(data) {
                selfoss.refreshStats(data.all, data.unread, data.starred);

                $('#content').html(data.entries);
                $(document).scrollTop(0);
                selfoss.events.entries();
                selfoss.events.search();
                
                // update tags
                selfoss.refreshTags(data.tags);
                
                // drop loaded sources
                if(selfoss.sourcesNavLoaded) {
                    $('#nav-sources li').remove();
                    selfoss.sourcesNavLoaded = false;
                }
                if(selfoss.filter.sourcesNav)
                    selfoss.refreshSources(data.sources);

                // clean up
                $('#content').removeClass('loading');
                selfoss.activeAjaxReq = null;
            },
            error: function(jqXHR, textStatus, errorThrown) {
                if (textStatus == "abort")
                    return;
                else if (textStatus == "parsererror")
                    location.reload();
                else if (errorThrown)
                    selfoss.showError('Load list error: '+
                                      textStatus+' '+errorThrown);
            }
        });
    },


    /**
     * refresh current stats.
     *
     * @return void
     */
    reloadStats: function() {
        if( Date.now() - selfoss.lastStatsUpdate < 5*60*1000 )
            return;

        var stats_url = $('base').attr('href')+'stats?tags=true';
        if( selfoss.filter.sourcesNav )
            stats_url = stats_url + '&sources=true';

        $.ajax({
            url: stats_url,
            type: 'GET',
            success: function(data) {
                if( data.unread>0 && $('.stream-empty').is(':visible') ) {
                    selfoss.reloadList();
                } else {
                    selfoss.refreshStats(data.all, data.unread, data.starred);
                    selfoss.refreshTags(data.tagshtml);

                    if( 'sourceshtml' in data )
                        selfoss.refreshSources(data.sourceshtml);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                selfoss.showError('Could not refresh stats: '+
                                  textStatus+' '+errorThrown);
            }
        });
    },
    

    /**
     * refresh stats.
     *
     * @return void
     * @param new all stats
     * @param new unread stats
     * @param new starred stats
     */
    refreshStats: function(all, unread, starred) {
        selfoss.lastStatsUpdate = Date.now();

        $('.nav-filter-newest span').html(all);
        $('.nav-filter-starred span').html(starred);

        selfoss.refreshUnread(unread);
    },

    
    /**
     * refresh unread stats.
     *
     * @return void
     * @param new unread stats
     */
    refreshUnread: function(unread) {
        $('span.unread-count').html(unread);

        // make unread itemcount red and show the unread count in the document
        // title
        if(unread>0) {
            $('span.unread-count').addClass('unread');
            $(document).attr('title', 'selfoss ('+unread+')');
        } else {
            $('span.unread-count').removeClass('unread');
            $(document).attr('title', 'selfoss');
        }
    },


    /**
     * refresh current tags.
     *
     * @return void
     */
    reloadTags: function() {
        $('#nav-tags').addClass('loading');
        $('#nav-tags li:not(:first)').remove();
        
        $.ajax({
            url: $('base').attr('href')+'tagslist',
            type: 'GET',
            success: function(data) {
                $('#nav-tags').append(data);
                selfoss.events.navigation();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                selfoss.showError('Load tags error: '+
                                  textStatus+' '+errorThrown);
            },
            complete: function(jqXHR, textStatus) {
                $('#nav-tags').removeClass('loading');
            }
        });
    },
    
    
    /**
     * refresh taglist.
     *
     * @return void
     * @param tags the new taglist as html
     */
    refreshTags: function(tags) {
        var currentTag = $('#nav-tags li').index($('#nav-tags .active'));
        $('.color').spectrum('destroy');
        $('#nav-tags li:not(:first)').remove();
        $('#nav-tags').append(tags);
        if(currentTag>=0)
            $('#nav-tags li:eq('+currentTag+')').addClass('active');
        selfoss.events.navigation();
    },
    
    
    sourcesNavLoaded: false,

    /**
     * refresh sources list.
     *
     * @return void
     * @param sources the new sourceslist as html
     */
    refreshSources: function(sources) {
        var currentSource = $('#nav-sources li').index($('#nav-sources .active'));
        $('#nav-sources li').remove();
        $('#nav-sources').append(sources);
        if(currentSource>=0)
            $('#nav-sources li:eq('+currentSource+')').addClass('active');
        selfoss.events.navigation();
    },
    
    
    /**
     * anonymize links
     *
     * @return void
     * @param parent element
     */
    anonymize: function(parent) {
        var anonymizer = $('#config').data('anonymizer');
        if(anonymizer.length>0) {
            parent.find('a').each(function(i,link) {
                link = $(link);
                if(typeof link.attr('href') != "undefined" && link.attr('href').indexOf(anonymizer)!=0) {
                    link.attr('href', anonymizer + link.attr('href'));
                }
            });
        }
    },
    
    
    /**
     * show error
     *
     * @return void
     * @param message string
     */
    showError: function(message) {
        if(typeof(message) == 'undefined') {
            var message = "Oops! Something went wrong";
        }
        var error = $('#error');
        error.html(message);
        error.show();
        window.setTimeout(function() {
            error.click();
        }, 10000);
        error.unbind('click').click(function() {
            error.fadeOut();
        });
    },

    /**
     * Setup fancyBox image viewer
     * @param content element
     * @param int
     */
    setupFancyBox: function(content, id) {
        // Close existing fancyBoxes
        $.fancybox.close();
        var images = $(content).find('a[href$=".jpg"],a[href$=".jpeg"],a[href$=".png"],a[href$=".gif"]');
        $(images).attr('rel', 'gallery-'+id).unbind('click');
        $(images).fancybox({
            helpers: {
                overlay: {
                    locked: false
                }
            }
        });
    }

};

selfoss.init();
