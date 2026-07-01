# PHPUnit Checker

`tool_phpunitchecker` is a Moodle admin tool for running PHPUnit test suites from the web UI and viewing the results in a structured report.

## Features

- Search and select PHPUnit test suites.
- Run selected suites from the browser.
- View a report with passed tests, failures, errors, skipped tests, assertions, and runtime.
- Optional confetti when all selected tests pass.

## Usage

Go to:

  ```text                                                                                                                                                       
  Site administration → Development → PhpUnit Checker
  ```                                                                                                   
                                                                                                                                                                
  Select one or more test suites and click Run PHPUnit Test Suites.                                                                                             
                                                                                                                                                                
  The plugin requires Moodle’s PHPUnit environment to be configured and ready.                                                                                  
                                                                                                                                                                
  ## Settings                                                                                                                                                   
                                                                                                                                                                
  Settings are available at:                                                                                                                                    

```text
  Site administration → Development → PHPUnit Checker settings                                                                                                  
```                                                                                                                                                         
  Available setting:                                                                                                                                            
                                                                                                                                                                
  - Enable confetti: Show confetti when selected test suites pass.                                                                                              
                                                                                                                                                                
  ## Notes                                                                                                                                                      
                                                                                                                                                                
  The report is based on PHPUnit’s JUnit XML output. Some PHPUnit issue types may not be distinguishable in that XML format.                                    
                            