/**
 * Quill Editor Integration for Disputo Topics
 * Automatically replaces textarea with Quill rich text editor
 */

class DisputoQuillEditor {
    constructor() {
        this.editors = new Map();
        this.init();
    }

    init() {
        // Initialize when DOM is ready; also try again if Quill isn't yet loaded
        const tryInit = () => {
            if (typeof window.Quill === 'undefined') {
                // Wait until Quill script loads
                setTimeout(tryInit, 50);
                return;
            }
            this.setupTextareaEditors();
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', tryInit);
        } else {
            tryInit();
        }
    }

    setupTextareaEditors() {
        // Find all textareas that should become Quill editors
        const textareas = document.querySelectorAll('textarea[name="description"], textarea.quill-source-textarea');
        
        textareas.forEach(textarea => {
            this.createQuillEditor(textarea);
        });
    }

    createQuillEditor(textarea) {
        if (!textarea || this.editors.has(textarea)) {
            return;
        }

        const initialContent = textarea.value || '';
        
        // If a container already exists next to the textarea, reuse it; otherwise create one
        let quillContainer = textarea.nextElementSibling;
        if (!(quillContainer && quillContainer.classList && quillContainer.classList.contains('quill-editor-container'))) {
            quillContainer = document.createElement('div');
            const editorId = 'quill-editor-' + Math.random().toString(36).substr(2, 9);
            quillContainer.id = editorId;
            quillContainer.className = 'quill-editor-container';
            textarea.parentNode.insertBefore(quillContainer, textarea.nextSibling);
        }
        // Hide original textarea (in case server didn't)
        textarea.style.display = 'none';
        
        // Initialize Quill
        const quill = new Quill('#' + quillContainer.id, {
            theme: 'snow',
            placeholder: textarea.getAttribute('placeholder') || 'Zadejte popis...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'blockquote'],
                    ['clean']
                ]
            }
        });
        
        // Set initial content
        if (initialContent) {
            quill.root.innerHTML = initialContent;
        }
        
        // Store editor reference
        this.editors.set(textarea, quill);
        
        // Setup form submission handler
        this.setupFormSubmission(textarea, quill);
        
        // Expose for debugging
        if (!window.__disputoQuillEditors) window.__disputoQuillEditors = [];
        window.__disputoQuillEditors.push(quill);
        
        return quill;
    }

    setupFormSubmission(textarea, quill) {
        const form = textarea.closest('form');
        if (!form) return;
        
        // Update textarea before form submission
        form.addEventListener('submit', () => {
            textarea.value = quill.root.innerHTML;
        });
        
        // Also update on input change for real-time sync
        quill.on('text-change', () => {
            textarea.value = quill.root.innerHTML;
        });
    }

    getEditor(textarea) {
        return this.editors.get(textarea);
    }

    destroyEditor(textarea) {
        const quill = this.editors.get(textarea);
        if (quill && quill.container) {
            quill.container.remove();
            this.editors.delete(textarea);
            textarea.style.display = '';
        }
    }
}

// Initialize when script loads
window.DisputoQuillEditor = new DisputoQuillEditor();