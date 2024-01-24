let toast = new Toasty();

let options = {
    classname: "toast",
    transition: "fade",
    insertBefore: true,
    duration: 6000,
    enableSounds: false,
    autoClose: true,
    progressBar: true,

    onShow: function (type) {
    },
    onHide: function (type) {
    },
    prependTo: document.body.childNodes[0]
};

toast.configure(options);

function displayToast(type = "", message = "", duration = 6000, options = {}) {

    toast.configure(options)

    if (type === "info") {
        toast.info(message, duration);
    } else if (type === "error") {
        toast.error(message, duration);
    } else if (type === "success") {
        toast.success(message, duration);
    } else if (type === "warning") {
        toast.warning(message, duration);
    }

    $('.toast').addClass('show');
}
