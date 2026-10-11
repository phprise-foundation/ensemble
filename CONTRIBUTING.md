# Contributing to PHPRise <Repository>

Thank you for considering contributing to PHPRise <Repository>! The contribution guidelines are as follows:

## Code of Conduct

This project and everyone participating in it is governed by the [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code.

## How Can I Contribute?

### Reporting Bugs

- Ensure the bug was not already reported by searching on GitHub under [Issues](https://github.com/otaku/application/issues).
- If you're unable to find an open issue addressing the problem, open a new one. Be sure to include a title and clear description, as much relevant information as possible, and a code sample or an executable test case demonstrating the expected behavior that is not occurring.

### Suggesting Enhancements

- Open a new issue with a clear title and detailed description of the suggested enhancement.
- Explain why this enhancement would be useful to other users.

### Pull Requests

1. Fork the repo and follow the [Gitflow](GITFLOW.md).
2. **Application Focus**: Respect Clean Architecture layers (Domain, Application, Infrastructure, Interface).
3. **Domain Sovereignty**: Protect the Domain from infrastructure or framework concerns.
4. **Testing**: Add integration and feature tests for use cases.
5. **Lean Core**: Move generic logic to new or existing Atomic Repositories.
6. Ensure the test suite passes.
7. Make sure your code follows the strict style guide below.

## Style Guide & Architecture

We follow a very strict set of rules to ensure high quality, maintainability, and fluid design.

### 1. Strict Typing & Standards
- **Strict Mode**: Every file must start with `declare(strict_types=1);`.
- **Strict Typing**: All properties, method arguments, and return types **must** be typed. Mixed should be avoided unless absolutely necessary.
- **PSR-12**: Follow the PSR-12 coding standard.
- **OOP**: Always use Object-Oriented Programming.

### 2. Object Calisthenics (Strict Enforcement)
1. **One level of indentation per method**: If you need more, extract a method.
2. **Don't use the `else` keyword**: Return early or check pre-conditions.
3. **Wrap all primitives and Strings**: Use Value Objects.
4. **First class collections**: Arrays should be wrapped in Collection classes.
5. **One dot per line**: Respect the Law of Demeter.
6. **Don't abbreviate**: Use full, descriptive names.
7. **Keep all entities small**: Classes under 50 lines, packages under 15 classes.
8. **No classes with more than two instance variables**: High cohesion is key.
9. **No getters/setters/properties**: Tell, Don't Ask. (Exception: DTOs may be data structures).

### 3. Clean Code & Simplicity
- **No Descriptive Comments**: Code must be self-explanatory through naming. Comments explain "why", never "what".
- **Complexity**: Keep code lean, cohesive, and simple. If it's complex, it's wrong.
- **Exceptions**:
    - Use specific exceptions, never generic `Exception`.
    - **Never** catch exceptions inside loops or recursions. Handle them at the execution level (top of the stack).

### 4. Architecture & Layers
- **No "Model" Layer**: Do not create a monolithic "Model". The model is the whole domain (Adapters, Validators, ValueObjects, etc.).
- **Specific Naming**: Avoid generic "Services". Use `StateProcessor`, `StateProvider`, `DataMapper`, `Query`, `Command`, `Connector`, `Adapter` etc.
- **Kill Middleware Layers**: If a service only delegates to a repository, delete it. Call the repository directly.
- **Infrastructure Ignorance**: The domain **must never** know about infrastructure. Never extend ORM classes (e.g., Eloquent Models).
- **No Arrays**: Do not use arrays for input/output. Use Objects or Collections.

### 5. Separation of Concerns
- **No Embedded Languages**: No HTML tags in JS/PHP. No JS strings in PHP.
- **Frontend/Backend Separation**: Use separate teams, tools, and infrastructure. No "magic wands" (fullstack coupling/monoliths) that cause future rework.
- **Componentization**: Componentize "everything" and use Composer.

### 6. Testing Manifesto
- **Do not over-test**: Don't test language contracts (e.g., if a method returns `string`, don't test that it returns a string).
- **Do not under-test**: Test everything that contains business logic or behavior.
- **Use the Right Test**:
    - **Controllers**: Use Integration/Feature/API tests. No unit logic to test here.
    - **Domain**: Use Unit tests.
- **No Duplication**: If a method is tested via another method's test, don't duplicate the test unless necessary for documentation.

## License

By contributing, you agree that your contributions will be licensed under its MIT License.
