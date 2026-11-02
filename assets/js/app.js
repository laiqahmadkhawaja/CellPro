// assets/js/app.js - CellPro ERP Application Script

$(document).ready(function() {
    // Sidebar toggle for mobile
    $('#mobile-sidebar-toggle').on('click', function() {
        $('#sidebar').toggleClass('show');
    });

    $(document).on('click', function(e) {
        if ($(window).width() < 992) {
            if (!$(e.target).closest('#sidebar, #mobile-sidebar-toggle').length) {
                $('#sidebar').removeClass('show');
            }
        }
    });

    // Side-Panel Drawer System (Zero Modals Rule)
    window.openSidePanel = function(panelId) {
        let $panel = $('#' + panelId);
        if ($panel.length) {
            $('#side-panel-overlay').addClass('show');
            $panel.addClass('open');
            $('body').css('overflow', 'hidden');
        }
    };

    window.closeSidePanel = function() {
        $('.side-panel').removeClass('open');
        $('#side-panel-overlay').removeClass('show');
        $('body').css('overflow', 'auto');
    };

    $(document).on('click', '[data-toggle-panel]', function(e) {
        e.preventDefault();
        let target = $(this).data('toggle-panel');
        openSidePanel(target);
    });

    $(document).on('click', '[data-close-panel], #side-panel-overlay', function(e) {
        e.preventDefault();
        closeSidePanel();
    });

    // Auto dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.flash-messages-container .alert').alert('close');
    }, 5000);
});
