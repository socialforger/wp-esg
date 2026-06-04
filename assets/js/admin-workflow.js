/**
 * WpEsg Administrative Workflow Manager Script
 * Handles Business Process Management state transitions asynchronously.
 */
(function($) {
    'use strict';

    $(function() {
        // Intercept workflow execution trigger buttons
        $('.js-wp-esg-transition-trigger').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var assessmentId = $button.data('assessment-id');
            var targetAction = $button.data('action-target'); // 'submit_to_review' or 'complete'
            
            if (!assessmentId || $button.hasClass('is-disabled')) {
                return;
            }

            if (!confirm('Are you sure you want to execute this state transition? Historical snapshots will be captured.')) {
                return;
            }

            $button.addClass('is-disabled').attr('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wp_esg_execute_workflow_transition',
                    nonce: wpEsgAdminMeta.workflow_nonce,
                    assessment_id: assessmentId,
                    transition: targetAction
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert('Workflow Error: ' + (response.data.message || 'Transition rejected by engine rules.'));
                        $button.removeClass('is-disabled').removeAttr('disabled');
                    }
                },
                error: function() {
                    alert('Critical Connection Failure: Unable to communicate with the processing server.');
                    $button.removeClass('is-disabled').removeAttr('disabled');
                }
            });
        });
    });

})(jQuery);
