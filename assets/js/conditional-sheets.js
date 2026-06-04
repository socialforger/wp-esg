/**
 * WpEsg Conditional Sheets & Form Interlacer
 * Dynamic layout builder matching ATECO tax parameters and capacity thresholds.
 */
(function($) {
    'use strict';

    var WpEsgFormEngine = {
        meta: {},
        activeCategory: 'none',
        hasPgsPact: false,

        init: function() {
            this.meta = window.wpEsgFormContext || {};
            this.bindEvents();
            this.runStructuralAudit();
        },

        bindEvents: function() {
            var self = this;

            // Step 1 Inputs: Automatically trigger recalculations on changes
            $('#company_tax_id, #business_code, #q0102, #company_country').on('change input', function() {
                self.runStructuralAudit();
            });

            // Frontend Multi-step Navigation Tab Switchboard
            $('.js-wp-esg-tab-trigger').on('click', function(e) {
                e.preventDefault();
                var targetPanel = $(this).data('target-panel');
                
                $('.js-wp-esg-tab-trigger').removeClass('is-active');
                $(this).addClass('is-active');
                
                $('.wp-esg-section-panel').removeClass('is-active');
                $('#' + targetPanel).addClass('is-active');
            });
        },

        /**
         * Asynchronously resolves structural dependencies based on real-time inputs.
         */
        runStructuralAudit: function() {
            var self = this;
            
            var payload = {
                country: $('#company_country').val() || 'IT',
                business_code: $('#business_code').val() || '',
                employee_count: parseInt($('#q0102').val(), 10) || 0
            };

            // Call internal routing system configuration parameters
            $.ajax({
                url: self.meta.ajax_url,
                type: 'GET',
                dataType: 'json',
                data: {
                    action: 'wp_esg_resolve_form_context',
                    country: payload.country,
                    business_code: payload.business_code,
                    employee_count: payload.employee_count,
                    nonce: self.meta.context_nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.adjustFormInterface(response.data);
                    }
                }
            });
        },

        /**
         * Dynamically adjusts form layout tabs and visibility rules.
         */
        adjustFormInterface: function(context) {
            // 1. Enforce Employee-Count Visibility Scope Rules (Short vs Long)
            if (context.company_size_scope === 'Short') {
                $('.wp-esg-field-node[data-scope="Long"]').addClass('is-hidden-field').hide();
                $('.wp-esg-scope-notice-short').show();
                $('.wp-esg-scope-notice-long').hide();
            } else {
                $('.wp-esg-field-node[data-scope="Long"]').removeClass('is-hidden-field').show();
                $('.wp-esg-scope-notice-short').hide();
                $('.wp-esg-scope-notice-long').show();
            }

            // 2. Toggle Participatory PGS Pact Section based on membership status
            if (context.active_pgs_pact) {
                $('.js-wp-esg-tab-nav[data-section="pgs"]').removeClass('is-hidden');
            } else {
                $('.js-wp-esg-tab-nav[data-section="pgs"]').addClass('is-hidden');
                if ($('#panel_pgs').hasClass('is-active')) {
                    $('.js-wp-esg-tab-trigger[data-target-panel="panel_openesea"]').click();
                }
            }

            // 3. Mount and Unmount Market Category Blueprints dynamically
            if (context.product_category !== 'none' && context.product_category !== this.activeCategory) {
                this.activeCategory = context.product_category;
                this.loadMarketCategoryFields(context.product_category);
            } else if (context.product_category === 'none') {
                this.activeCategory = 'none';
                $('.js-wp-esg-tab-nav[data-section="market_category"]').addClass('is-hidden');
                if ($('#panel_market_category').hasClass('is-active')) {
                    $('.js-wp-esg-tab-trigger[data-target-panel="panel_openesea"]').click();
                }
            }
        },

        /**
         * Injects localized layout strings into the target market vertical tab.
         */
        loadMarketCategoryFields: function(categoryId) {
            var self = this;
            var container = $('#panel_market_category_fields');
            container.html('<div class="wp-esg-spinner">Loading localized catalog criteria...</div>');

            $.ajax({
                url: self.meta.ajax_url,
                type: 'GET',
                dataType: 'json',
                data: {
                    action: 'wp_esg_get_localized_form_blueprint',
                    category_id: categoryId,
                    nonce: self.meta.blueprint_nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.renderInjectedFields(response.data, container);
                        $('.js-wp-esg-tab-nav[data-section="market_category"]').removeClass('is-hidden').find('span').text(response.data.label);
                    } else {
                        container.html('<div class="wp-esg-error">Error: Unable to mount structural metadata blueprints.</div>');
                    }
                }
            });
        },

        renderInjectedFields: function(data, targetContainer) {
            var html = '';
            
            $.each(data.localization, function(sectionKey, section) {
                html += '<div class="wp-esg-form-section-group">';
                html += '<h3>' + section.label + '</h3>';
                
                $.each(section.fields, function(fieldKey, field) {
                    html += '<div class="wp-esg-field-node" data-field-id="' + fieldKey + '">';
                    html += '<label for="' + fieldKey + '">' + field.label + '</label>';
                    html += '<textarea name="' + fieldKey + '" id="' + fieldKey + '" class="wp-esg-input-control"></textarea>';
                    if (field.help_inline) {
                        html += '<span class="wp-esg-help-inline">' + field.help_inline + '</span>';
                    }
                    html += '</div>';
                });
                
                html += '</div>';
            });

            targetContainer.html(html);
        }
    };

    $(function() {
        WpEsgFormEngine.init();
    });

})(jQuery);
