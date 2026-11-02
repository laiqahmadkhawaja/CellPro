// assets/js/app.js - CellPro ERP Application Script

// Universal Searchable Select (Select2)
window.initSearchableSelects = function($scope) {
    if (typeof $.fn.select2 === "undefined") return;

    let $elements;
    if ($scope) {
        $elements = $scope.is("select") ? $scope : $scope.find("select");
    } else {
        $elements = $("select");
    }
    $elements.each(function() {
        let $s = $(this);
        if ($s.hasClass("select2-hidden-accessible") ||
            $s.hasClass("no-search") ||
            $s.hasClass("no-select2") ||
            $s.closest(".top-navbar").length ||
            $s.is('[onchange*="switch_branch"]')) {
            return;
        }

        let $parent = $s.closest(".side-panel").length ? $s.closest(".side-panel") : null;

        $s.select2({
            theme: "bootstrap-5",
            width: "100%",
            dropdownParent: $parent,
            dropdownAutoWidth: true,
            placeholder: $s.find("option[value='']").first().text() || undefined,
            allowClear: false
        });
    });
};

$(document).ready(function() {
    initSearchableSelects();
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
            $('#main-content').css('overflow', 'hidden');
            if (typeof initSearchableSelects === 'function') { initSearchableSelects($panel); }
        }
    };

    window.closeSidePanel = function() {
        $('.side-panel').removeClass('open');
        $('#side-panel-overlay').removeClass('show');
        $('#main-content').css('overflow', 'auto');
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
