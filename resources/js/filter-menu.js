export function closeFilterAfterFocusChange(menu, currentDocument = document) {
    setTimeout(() => {
        if (menu.open && !menu.contains(currentDocument.activeElement)) {
            menu.open = false;
        }
    }, 0);
}
