/* Lib */

var recommend_ajax_call = 'plugin_recommend';

function sack_form(form, fnc) {
    var ajax = new sack(DOKU_BASE + 'lib/exe/ajax.php');
    ajax.setVar('call', recommend_ajax_call);
    function serializeByTag(tag) {
        var inps = form.getElementsByTagName(tag);
        for (var inp in inps) {
            if (inps[inp].name) {
                ajax.setVar(inps[inp].name, inps[inp].value);
            }
        }
    }
    serializeByTag('input');
    serializeByTag('textarea');
    ajax.onCompletion = fnc;
    ajax.runAJAX();
    return false;
}

function bind(fnc, val) {
    return function () {
        return fnc(val);
    };
}

function change_form_handler(forms, handler) {
    if (!forms) return;
    for (var formid in forms) {
        var form = forms[formid];
        form.onsubmit = bind(handler, form);
    }
}

/* Recommend */

function recommend_box(content) {
    var div = $('recommend_box');
    if (!div) {
        div = document.createElement('div');
        div.id = 'recommend_box';
    } else if (content === '') {
        div.parentNode.removeChild(div);
        return;
    }
    div.innerHTML = content;
    getElementsByClass('stylehead', document, 'div')[0].appendChild(div);
    return div;
}

function recommend_handle() {
    if (this.response === "AJAX call '" + recommend_ajax_call + "' unknown!\n") {
        /* No user logged in. */
        return;
    }
    if (this.responseStatus[0] === 204) {
        recommend_box('');
        return;
    }

    var box = recommend_box(this.response);
    box.getElementsByTagName('label')[0].focus();
    change_form_handler(box.getElementsByTagName('form'),
                        function (form) {return sack_form(form, recommend_handle); });

    var inputs = box.getElementsByTagName('input');
    inputs[inputs.length - 1].onclick = function() {recommend_box(''); return false;};
}

addInitEvent(function () {
                change_form_handler(getElementsByClass('btn_recommend', document, 'form'),
                                    function (form) {return sack_form(form, recommend_handle); });
             });
