// Monaco Editor Management
let editor = null;

function initMonacoEditor(containerId, initialCode = '') {
    return new Promise((resolve) => {
        // Load Monaco Editor dynamically using AMD loader
        require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.39.0/min/vs' } });
        
        require(['vs/editor/editor.main'], function () {
            editor = monaco.editor.create(document.getElementById(containerId), {
                value: initialCode || `#include <iostream>\n\nusing namespace std;\n\nint main() {\n    // Write your C++ code here\n    cout << "Hello World!" << endl;\n    return 0;\n}`,
                language: 'cpp',
                theme: document.documentElement.classList.contains('dark') ? 'vs-dark' : 'vs',
                fontSize: 14,
                fontFamily: 'Fira Code, Courier New, monospace',
                automaticLayout: true,
                minimap: { enabled: false },
                cursorBlinking: 'smooth',
                cursorSmoothCaretAnimation: 'on',
                lineHeight: 22,
                tabSize: 4,
            });

            // Listen for theme changes and adjust Monaco
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.attributeName === 'class') {
                        const isDark = document.documentElement.classList.contains('dark');
                        monaco.editor.setTheme(isDark ? 'vs-dark' : 'vs');
                    }
                });
            });
            observer.observe(document.documentElement, { attributes: true });

            resolve(editor);
        });
    });
}

function getEditorCode() {
    return editor ? editor.getValue() : '';
}

function setEditorCode(code) {
    if (editor) {
        editor.setValue(code);
    }
}

function updateEditorFontSize(size) {
    if (editor) {
        editor.updateOptions({ fontSize: size });
    }
}
