/*!
 * JavaScript Reply Comment
 */
window.CommentFormReply = function(config) {

    var defaults = {
        comment: 'comment-list',
        form: 'comment-form',
        reply: 'comment-reply',
        cancel: 'comment-form-cancel',
        cancelText: 'Cancel Reply'
    };

    for (var i in defaults) {
        config[i] = typeof config[i] == "undefined" ? defaults[i] : config[i];
    }

    var list = document.getElementById(config.comment),
        rep = list.getElementsByTagName('a'),
        form = document.getElementById(config.form),
        cla = new RegExp('(^| )' + config.reply + '( |$)', 'i');

    var div = document.createElement('div'),
        cancel = document.createElement('a');
        cancel.href = '#' + config.cancelText.toLowerCase().replace(/ /g, "-");
        cancel.innerHTML = config.cancelText;
        div.className = config.cancel;
        div.appendChild(cancel);

    cancel.onclick = function() {
        list.parentNode.appendChild(form);
        form.removeChild(div);
        form.parent.value = "-";
        /*
        form.message.value = "";
        */
        form.message.focus();
        return false;
    };

    function clickReply(elem) {
        elem.onclick = function() {
            var id = this.getAttribute('data-comment-id'),
                name = this.getAttribute('data-comment-name');
            this.parentNode.parentNode.appendChild(form);
            form.appendChild(div);
            form.parent.value = id;
            form.message.focus();
            /*
            form.message.value = '@' + name + ' ';
            form.message.selectionStart = name.length + 2;
            form.message.selectionEnd = name.length + 2;
            */
            return false;
        };
    }

    for (var i = 0, ien = rep.length; i < ien; ++i) {
        if (cla.test(rep[i].className)) {
            clickReply(rep[i]);
        }
    }

};
