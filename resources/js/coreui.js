/** ***
* CONFIGURATION
*/
// Main navigation
$.navigation = $('nav > ul.nav');

$.panelIconOpened = 'icon-arrow-up';
$.panelIconClosed = 'icon-arrow-down';

// Default colours
$.brandPrimary = '#20a8d8';
$.brandSuccess = '#4dbd74';
$.brandInfo = '#63c2de';
$.brandWarning = '#f8cb00';
$.brandDanger = '#f86c6b';

$.grayDark = '#2a2c36';
$.gray = '#55595c';
$.grayLight = '#818a91';
$.grayLighter = '#d1d4d7';
$.grayLightest = '#f8f9fa';

'use strict';

/** **
* MAIN NAVIGATION
*/

$(document).ready(($) => {
    // Add class .active to current link
    $.navigation.find('a').each(function() {
        let cUrl = String(window.location).split('?')[0];

        if (cUrl.substr(cUrl.length - 1) == '#') {
            cUrl = cUrl.slice(0, -1);
        }

        if ($($(this))[0].href == cUrl) {
            $(this).addClass('active');

            $(this).parents('ul').add(this).each(function() {
                $(this).parent().addClass('open');
            });
        }
    });

    // Dropdown Menu
    $.navigation.on('click', 'a', function(e) {
        if ($.ajaxLoad) {
            e.preventDefault();
        }

        if ($(this).hasClass('nav-dropdown-toggle')) {
            $(this).parent().toggleClass('open');
            resizeBroadcast();
        }
    });

    function resizeBroadcast() {
        let timesRun = 0;
        var interval = setInterval(() => {
            timesRun += 1;
            if (timesRun === 5) {
                clearInterval(interval);
            }
            window.dispatchEvent(new Event('resize'));
        }, 62.5);
    }

    /* ---------- Main Menu Open/Close, Min/Full ---------- */
    if (localStorage.sidebarMenuState === 'true') {
        document.body.classList.add('sidebar-minimized');
    }

    document.querySelector('.sidebar').style.display = 'block';

    $('.navbar-toggler').click(function() {
        if (this.classList.contains('sidebar-toggler')) {
            document.body.classList.toggle('sidebar-hidden');
            resizeBroadcast();
        }

        if (this.classList.contains('sidebar-minimizer')) {
            document.body.classList.toggle('sidebar-minimized');
            localStorage.sidebarMenuState = localStorage.sidebarMenuState === 'true' ? 'false' : 'true';
            resizeBroadcast();
        }

        if (this.classList.contains('aside-menu-toggler')) {
            document.body.classList.toggle('aside-menu-hidden');
            resizeBroadcast();
        }

        if (this.classList.contains('mobile-sidebar-toggler')) {
            document.body.classList.toggle('sidebar-mobile-show');
            resizeBroadcast();
        }
    });

    $('.sidebar-close').click(() => {
        document.body.classList.toggle('sidebar-opened');
    });

    /* ---------- Disable moving to top ---------- */
    $('a[href="#"][data-top!=true]').click((e) => {
        e.preventDefault();
    });
    $('a[href="#"]').click((e) => {

        if ($.ajaxLoad) {
            e.preventDefault();
        }

        if ($(this).hasClass('nav-dropdown-toggle')) {
            $(this).parent().toggleClass('open');
            resizeBroadcast();
        }

    });
});

/** **
* CARDS ACTIONS
*/
$(document).on('click', '.card-actions a', function(e) {
    e.preventDefault();

    if ($(this).hasClass('btn-close')) {
        $(this).parent().parent().parent()
            .fadeOut();
    }
    else if ($(this).hasClass('btn-minimize')) {
        const $target = $(this).parent().parent().next('.card-block');
        if (!$(this).hasClass('collapsed')) {
            $('i', $(this)).removeClass($.panelIconOpened).addClass($.panelIconClosed);
        }
        else {
            $('i', $(this)).removeClass($.panelIconClosed).addClass($.panelIconOpened);
        }
    }
    else if ($(this).hasClass('btn-setting')) {
        $('#myModal').modal('show');
    }
});

function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function init(url) {
    /* ---------- Tooltip ---------- */
    $('[rel="tooltip"],[data-rel="tooltip"]').tooltip({ placement: 'bottom', delay: { show: 400, hide: 200 } });

    /* ---------- Popover ---------- */
    $('[rel="popover"],[data-rel="popover"],[data-toggle="popover"]').popover();
}
