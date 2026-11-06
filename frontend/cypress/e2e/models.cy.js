describe('model catalog API', () => {
  it('serves GET /api/models through the application entrypoint', () => {
    cy.request('POST', '/api/auth/login', { login: 'admin', password: 'admin1234' })
      .its('body.token')
      .then((token) => {
        cy.request({ url: '/api/models', headers: { Authorization: `Bearer ${token}` } })
          .its('body')
          .then((body) => {
            expect(body.default).to.eq('ens26.1-flash')
            expect(body.models).to.have.length(5)
            expect(body.models[0]).to.not.have.property('internal')
          })
      })
  })
})
