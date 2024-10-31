class PPLib {
    constructor(selector, context = document) {
        this.elements = typeof selector === 'string' ? this.get(selector, context) : [selector];
    }

    static template(tpl, data) {
        return tpl.replace(/\{\{(\w+)\}\}/g, (_, key) => data[key] || '');
    }

    static strToHTML(str) {
        const elem = document.createElement('div');
        elem.innerHTML = str;
        return elem.firstElementChild;
    }
}

const _$ = (selector, context = document) => new PPLib(selector, context);

// Attach static methods to the wrapper function
Object.getOwnPropertyNames(PPLib).forEach(prop => {
    if (typeof PPLib[prop] === 'function' && prop !== 'prototype') {
        _$[prop] = PPLib[prop];
    }
});

export { _$ };
