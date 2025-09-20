import { chromium, FullConfig } from '@playwright/test'

async function globalSetup(config: FullConfig) {
  // Launch browser for authentication setup
  const browser = await chromium.launch()
  const context = await browser.newContext()
  const page = await context.newPage()

  try {
    // Setup test database
    console.log('Setting up test database...')
    
    // Navigate to application
    await page.goto(config.projects[0].use?.baseURL || 'http://localhost:8000')
    
    // Wait for application to be ready
    await page.waitForLoadState('networkidle')
    
    console.log('Application is ready for testing')
    
  } catch (error) {
    console.error('Global setup failed:', error)
    throw error
  } finally {
    await browser.close()
  }
}

export default globalSetup