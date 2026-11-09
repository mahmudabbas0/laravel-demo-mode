(() => {
    document.querySelectorAll('[data-demo-mode-toast]').forEach((toast) => {
        const button = toast.querySelector('[data-demo-mode-dismiss]');
        const duration = Number(toast.dataset.duration);
        let timer;

        const hide = () => {
            clearTimeout(timer);
            toast.hidden = true;
        };

        const schedule = () => {
            clearTimeout(timer);
            if (duration > 0 && !toast.matches(':hover, :focus-within')) {
                timer = setTimeout(hide, duration);
            }
        };

        if (button) {
            button.hidden = false;
            button.addEventListener('click', hide);
        }

        toast.addEventListener('mouseenter', () => clearTimeout(timer));
        toast.addEventListener('focusin', () => clearTimeout(timer));
        toast.addEventListener('mouseleave', schedule);
        toast.addEventListener('focusout', () => setTimeout(schedule, 0));
        schedule();
    });
})();
