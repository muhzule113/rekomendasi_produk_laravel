const assert = require('node:assert/strict');
const fs = require('node:fs');
const test = require('node:test');
const vm = require('node:vm');

class FakeElement {
    constructor(tagName = '') {
        this.tagName = tagName.toUpperCase();
        this.children = [];
        this.className = '';
        this._textContent = '';
        this.innerHTML = '';
        this.classList = {
            add: () => {},
            remove: () => {},
        };
    }

    get textContent() {
        return this.children.length
            ? this.children.map((child) => child.textContent || '').join('')
            : this._textContent;
    }

    set textContent(value) {
        this._textContent = String(value ?? '');
        this.children = [];
    }

    append(...nodes) {
        this.children.push(...nodes);
    }

    replaceChildren(...nodes) {
        this.children = nodes;
    }
}

function loadAdminScript() {
    const ids = [
        'confirm-title',
        'confirm-desc',
        'confirm-icon',
        'confirm-icon-wrap',
        'confirm-ok-btn',
        'admin-confirm-overlay',
    ];
    const elements = Object.fromEntries(ids.map((id) => [id, new FakeElement()]));
    const document = {
        addEventListener: () => {},
        removeEventListener: () => {},
        getElementById: (id) => elements[id] || new FakeElement(),
        createElement: (tagName) => new FakeElement(tagName),
        createTextNode: (text) => ({ type: 'text', textContent: String(text) }),
        querySelectorAll: () => [],
    };
    const context = { document, console, setTimeout };
    vm.runInNewContext(
        fs.readFileSync('public/assets/js/admin.js', 'utf8'),
        context,
        { filename: 'public/assets/js/admin.js' },
    );

    return { context, elements };
}

test('adminConfirm renders structured emphasis and line break without raw HTML', () => {
    const { context, elements } = loadAdminScript();

    context.adminConfirm({
        title: 'Ubah Status Pesanan?',
        desc: {
            before: 'Yakin ingin mengubah status menjadi ',
            emphasis: 'Selesai',
            after: '?',
            note: 'Status ini bersifat final dan tidak dapat diubah kembali.',
        },
    });

    assert.equal(
        elements['confirm-desc'].textContent,
        'Yakin ingin mengubah status menjadi Selesai?Status ini bersifat final dan tidak dapat diubah kembali.',
    );
    assert.deepEqual(
        elements['confirm-desc'].children.map((child) => child.textContent),
        [
            'Yakin ingin mengubah status menjadi ',
            'Selesai',
            '?',
            '',
            'Status ini bersifat final dan tidak dapat diubah kembali.',
        ],
    );
    assert.equal(elements['confirm-desc'].children[1].tagName, 'STRONG');
    assert.equal(elements['confirm-desc'].children[3].tagName, 'BR');
});

test('adminConfirm keeps regular descriptions as plain text', () => {
    const { context, elements } = loadAdminScript();

    context.adminConfirm({ desc: 'Deskripsi aman <b>tidak dirender</b>.' });

    assert.equal(elements['confirm-desc'].textContent, 'Deskripsi aman <b>tidak dirender</b>.');
    assert.deepEqual(elements['confirm-desc'].children, []);
});

console.log('admin confirmation tests loaded');
