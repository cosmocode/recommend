/**
 * jQuery rewrite is almost completely taken from infomail plugin
 * @author Andreas Gohr
 */

const recommend = {
    $dialog: null,

    /**
     * Attach click handler to our link
     */
    init: function () {
        jQuery('a.plugin_recommend').click(recommend.initform);
        jQuery('li.recommend a').click(recommend.initform);
    },

    /**
     * Initializes the form dialog on click
     *
     * @param {Event} e
     */
    initform: function (e) {
        e.stopPropagation();
        e.preventDefault();

        let url = new URL(e.currentTarget.href);
        // searchParams only works, when no URL rewriting takes place
        // from Dokuwiki - else there is no parameter id and this
        // returns null
        let id = url.searchParams.get('id');
        if ( id === null ) {
            // Convert url to string an get the last part without
            // any parameters from actions and the like
            url = String(url);
            id = url.split('/').pop().split('?')[0];
        }

        recommend.$dialog = jQuery('<div></div>');
        recommend.$dialog.dialog(
            {
                modal: true,
                title: LANG.plugins.recommend.formname + ' ' + id,
                minWidth: 680,
                height: "auto",
                close: function () {
                    recommend.$dialog.dialog('destroy')
                }
            }
        );

        jQuery.get(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                'call': 'recommend',
                'id': id
            },
            recommend.handleResult,
            'html'
        );
    },

    /**
     * Display the result and attach handlers
     *
     * @param {string} data The HTML
     */
    handleResult: function (data) {

        function commasplit( val ) {
            return val.split( /,\s*/ );
        }

        recommend.$dialog.html(data);
        recommend.$dialog.find('button[type=reset]').click(recommend.cancel);
        recommend.$dialog.find('button[type=submit]').click(recommend.send);
        recommend.$dialog.find('input[name=r_email]').autocomplete({
            source: function (request, cb) {
                let term = request.term;
                term = commasplit(term).pop();

                const payload = {};
                payload['call'] = 'plugin_recommend_ac';
                payload['search'] = term;

                jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', payload, cb, 'json')
                    .fail(function (result) {
                        if (result.responseJSON) {
                            if (result.responseJSON.stacktrace) {
                                console.error(result.responseJSON.error + "\n" + result.responseJSON.stacktrace);
                            }
                            alert(result.responseJSON.error);
                        } else {
                            // some fatal error occurred, get a text only version of the response
                            alert(jQuery(result.responseText).text());
                        }
                    });
            },
            focus: function() {
                // prevent value inserted on focus
                return false;
            },
            select: function( event, ui ) {
                let terms = commasplit( this.value );
                // remove the current input
                terms.pop();
                // add the selected item
                terms.push( ui.item.value );
                // add placeholder to get the comma-and-space at the end
                terms.push( "" );
                this.value = terms.join( ", " );
                return false;
            }
        });
    },

    /**
     * Cancel the recommend form
     *
     * @param {Event} e
     */
    cancel: function (e) {
        e.preventDefault();
        e.stopPropagation();
        recommend.$dialog.dialog('destroy');
    },

    /**
     * Serialize the form and send it
     *
     * @param {Event} e
     */
    send: function (e) {
        e.preventDefault();
        e.stopPropagation();

        let data = recommend.$dialog.find('form').serialize();
        data = data + '&call=recommend';

        recommend.$dialog.html('...');
        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            data,
            recommend.handleResult,
            'html'
        );
    }
};
jQuery(recommend.init);
