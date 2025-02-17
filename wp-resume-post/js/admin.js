jQuery(document).ready(function($) {
    // Function to toggle API key rows based on selected API
    function toggleApiKeyRows() {
        var selectedApi = $('#resume_post_api_type').val();
        $('.api-key-row').hide();
        $('.' + selectedApi + '-row').show();
    }

    // Function to toggle auth method sections
    function toggleAuthMethod() {
        var selectedApi = $('#resume_post_api_type').val();
        var selectedMethod = $('input[name="' + selectedApi + '_auth_method"]:checked').val();
        
        $('.auth-section').hide();
        $('.' + selectedApi + '-' + selectedMethod + '-section').show();
    }

    // Initial toggle on page load
    toggleApiKeyRows();
    toggleAuthMethod();

    // Toggle on API type change
    $('#resume_post_api_type').on('change', function() {
        toggleApiKeyRows();
        toggleAuthMethod();
    });

    // Toggle on auth method change
    $('input[name="chatgpt_auth_method"], input[name="deepseek_auth_method"]').on('change', function() {
        toggleAuthMethod();
    });

    // ChatGPT API test
    $('#test_chatgpt_api').on('click', function() {
        var button = $(this);
        var resultSpan = $('#chatgpt_api_test_result');
        
        button.prop('disabled', true);
        resultSpan.html('Testing...');
        
        $.ajax({
            url: resumePostAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'test_chatgpt_api',
                nonce: resumePostAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: green;">' + response.data + '</span>');
                } else {
                    resultSpan.html('<span style="color: red;">' + response.data + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: red;">Connection error. Please try again.</span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });

    // DeepSeek API test
    $('#test_deepseek_api').on('click', function() {
        var button = $(this);
        var resultSpan = $('#deepseek_api_test_result');
        
        button.prop('disabled', true);
        resultSpan.html('Testing...');
        
        $.ajax({
            url: resumePostAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'test_deepseek_api',
                nonce: resumePostAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: green;">' + response.data + '</span>');
                } else {
                    resultSpan.html('<span style="color: red;">' + response.data + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: red;">Connection error. Please try again.</span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });

    // ChatGPT Email Login test
    $('#test_chatgpt_email').on('click', function() {
        var button = $(this);
        var resultSpan = $('#chatgpt_email_test_result');
        var email = $('#resume_post_chatgpt_email').val();
        var password = $('#resume_post_chatgpt_password').val();
        
        if (!email || !password) {
            resultSpan.html('<span style="color: red;">Please enter both email and password.</span>');
            return;
        }
        
        button.prop('disabled', true);
        resultSpan.html('Testing...');
        
        $.ajax({
            url: resumePostAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'test_chatgpt_email',
                email: email,
                password: password,
                nonce: resumePostAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: green;">' + response.data + '</span>');
                } else {
                    resultSpan.html('<span style="color: red;">' + response.data + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: red;">Connection error. Please try again.</span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });

    // DeepSeek Email Login test
    $('#test_deepseek_email').on('click', function() {
        var button = $(this);
        var resultSpan = $('#deepseek_email_test_result');
        var email = $('#resume_post_deepseek_email').val();
        var password = $('#resume_post_deepseek_password').val();
        
        if (!email || !password) {
            resultSpan.html('<span style="color: red;">Please enter both email and password.</span>');
            return;
        }
        
        button.prop('disabled', true);
        resultSpan.html('Testing...');
        
        $.ajax({
            url: resumePostAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'test_deepseek_email',
                email: email,
                password: password,
                nonce: resumePostAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: green;">' + response.data + '</span>');
                } else {
                    resultSpan.html('<span style="color: red;">' + response.data + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: red;">Connection error. Please try again.</span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});