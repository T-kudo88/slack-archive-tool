import { FullConfig } from '@playwright/test'

async function globalTeardown(config: FullConfig) {
  try {
    console.log('Cleaning up test environment...')
    
    // Any cleanup logic here
    // For example, clearing test data, stopping services, etc.
    
    console.log('Test environment cleanup completed')
  } catch (error) {
    console.error('Global teardown failed:', error)
  }
}

export default globalTeardown