<?php

namespace QueryPathTests;

/**
 * Makes sure the examples in examples/ keep working.
 *
 * Only the offline examples are covered here, so that this suite stays fast and
 * deterministic. The ones that call a third-party service are run separately by
 * .github/workflows/examples.yml - see ExampleRunner::NETWORK_EXAMPLES.
 *
 * @see ExampleRunner
 */
class ExamplesTest extends TestCase
{
	/**
	 * Every offline example, keyed by name so PHPUnit reports which one failed.
	 *
	 * @return array
	 */
	public function offlineExampleProvider(): array
	{
		$cases = [];

		foreach (ExampleRunner::offline() as $name => $path) {
			$cases[$name] = [$name, $path];
		}

		return $cases;
	}

	/**
	 * @dataProvider offlineExampleProvider
	 *
	 * @param string $name
	 * @param string $path
	 */
	public function testExampleRunsCleanly($name, $path)
	{
		$result = ExampleRunner::run($path, true);

		$this->assertTrue(
			$result['passed'],
			sprintf(
				"The %s example did not run cleanly.\n\n%s\n\nOutput:\n%s",
				$name,
				$result['reason'],
				trim($result['output']) === '' ? '(no output)' : trim($result['output'])
			)
		);
	}

	/**
	 * Guards the network list itself: every name in it has to correspond to a
	 * real example, or the offline suite silently stops covering something.
	 */
	public function testNetworkExampleListIsAccurate()
	{
		$missing = array_diff(ExampleRunner::NETWORK_EXAMPLES, array_keys(ExampleRunner::all()));

		$this->assertSame(
			[],
			array_values($missing),
			'ExampleRunner::NETWORK_EXAMPLES names examples that do not exist: '
			. implode(', ', $missing)
		);
	}

	/**
	 * Every example directory needs an index.php, otherwise it is not runnable
	 * and nothing above will notice it exists.
	 */
	public function testEveryExampleDirectoryIsRunnable()
	{
		$directories = glob(ExampleRunner::examplesDir() . '/*', GLOB_ONLYDIR);

		$withoutEntryPoint = [];

		foreach ($directories as $directory) {
			if (! file_exists($directory . '/index.php')) {
				$withoutEntryPoint[] = basename($directory);
			}
		}

		$this->assertSame(
			[],
			$withoutEntryPoint,
			'Example directories with no index.php: ' . implode(', ', $withoutEntryPoint)
		);
	}
}
