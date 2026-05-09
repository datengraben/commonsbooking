describe('System Health Page', () => {
    const url = '/wp-admin/admin.php?page=cb-system-health';

    beforeEach(() => {
        cy.loginAs('admin');
    });

    it('loads the system health page without errors', () => {
        cy.visit(url);
        cy.get('h1').should('contain', 'System Health');
        cy.screenshot('system-health_loaded');
    });

    it('shows the System Status tab with health check rows', () => {
        cy.visit(url);
        cy.get('#cb-system-status').should('be.visible');
        cy.get('#cb-system-status table tbody tr').should('have.length.greaterThan', 0);
        cy.screenshot('system-health_status-tab');
    });

    it('switches to the Error Log tab when clicked', () => {
        cy.visit(url);
        cy.get('a[data-tab="cb-error-log"]').click();
        cy.get('#cb-error-log').should('be.visible');
        cy.get('#cb-system-status').should('not.be.visible');
        cy.screenshot('system-health_error-log-tab');
    });

    it('shows a Clear Error Log button in the Error Log tab', () => {
        cy.visit(url);
        cy.get('a[data-tab="cb-error-log"]').click();
        cy.get('#cb-error-log button[type="submit"]').should('contain', 'Clear Error Log');
    });

    it('shows System Health in the CommonsBooking admin menu', () => {
        cy.visit('/wp-admin/admin.php?page=cb-dashboard');
        cy.get('#adminmenu').should('contain', 'System Health');
    });
});
