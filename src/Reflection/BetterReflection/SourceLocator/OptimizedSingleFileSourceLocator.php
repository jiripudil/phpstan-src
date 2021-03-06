<?php declare(strict_types = 1);

namespace PHPStan\Reflection\BetterReflection\SourceLocator;

use PhpParser\Node\Expr\FuncCall;
use Roave\BetterReflection\Identifier\Identifier;
use Roave\BetterReflection\Identifier\IdentifierType;
use Roave\BetterReflection\Reflection\Reflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionConstant;
use Roave\BetterReflection\Reflection\ReflectionFunction;
use Roave\BetterReflection\Reflector\Reflector;
use Roave\BetterReflection\SourceLocator\Ast\Strategy\NodeToReflection;
use Roave\BetterReflection\SourceLocator\Type\SourceLocator;

class OptimizedSingleFileSourceLocator implements SourceLocator
{

	private \PHPStan\Reflection\BetterReflection\SourceLocator\FileNodesFetcher $fileNodesFetcher;

	private string $fileName;

	private ?\PHPStan\Reflection\BetterReflection\SourceLocator\FetchedNodesResult $fetchedNodesResult = null;

	public function __construct(
		FileNodesFetcher $fileNodesFetcher,
		string $fileName
	)
	{
		$this->fileNodesFetcher = $fileNodesFetcher;
		$this->fileName = $fileName;
	}

	public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
	{
		if ($this->fetchedNodesResult === null) {
			$this->fetchedNodesResult = $this->fileNodesFetcher->fetchNodes($this->fileName);
		}
		$nodeToReflection = new NodeToReflection();
		if ($identifier->isClass()) {
			$classNodes = $this->fetchedNodesResult->getClassNodes();
			$className = strtolower($identifier->getName());
			if (!array_key_exists($className, $classNodes)) {
				return null;
			}

			foreach ($classNodes[$className] as $classNode) {
				$classReflection = $nodeToReflection->__invoke(
					$reflector,
					$classNode->getNode(),
					$this->fetchedNodesResult->getLocatedSource(),
					$classNode->getNamespace()
				);
				if (!$classReflection instanceof ReflectionClass) {
					throw new \PHPStan\ShouldNotHappenException();
				}

				return $classReflection;
			}
		}

		if ($identifier->isFunction()) {
			$functionNodes = $this->fetchedNodesResult->getFunctionNodes();
			$functionName = strtolower($identifier->getName());
			if (!array_key_exists($functionName, $functionNodes)) {
				return null;
			}

			$functionReflection = $nodeToReflection->__invoke(
				$reflector,
				$functionNodes[$functionName]->getNode(),
				$this->fetchedNodesResult->getLocatedSource(),
				$functionNodes[$functionName]->getNamespace()
			);
			if (!$functionReflection instanceof ReflectionFunction) {
				throw new \PHPStan\ShouldNotHappenException();
			}

			return $functionReflection;
		}

		if ($identifier->isConstant()) {
			$constantNodes = $this->fetchedNodesResult->getConstantNodes();
			foreach ($constantNodes as $stmtConst) {
				if ($stmtConst->getNode() instanceof FuncCall) {
					$constantReflection = $nodeToReflection->__invoke(
						$reflector,
						$stmtConst->getNode(),
						$this->fetchedNodesResult->getLocatedSource(),
						$stmtConst->getNamespace()
					);
					if ($constantReflection === null) {
						continue;
					}
					if (!$constantReflection instanceof ReflectionConstant) {
						throw new \PHPStan\ShouldNotHappenException();
					}
					if ($constantReflection->getName() !== $identifier->getName()) {
						continue;
					}

					return $constantReflection;
				}

				foreach (array_keys($stmtConst->getNode()->consts) as $i) {
					$constantReflection = $nodeToReflection->__invoke(
						$reflector,
						$stmtConst->getNode(),
						$this->fetchedNodesResult->getLocatedSource(),
						$stmtConst->getNamespace(),
						$i
					);
					if ($constantReflection === null) {
						continue;
					}
					if (!$constantReflection instanceof ReflectionConstant) {
						throw new \PHPStan\ShouldNotHappenException();
					}
					if ($constantReflection->getName() !== $identifier->getName()) {
						continue;
					}

					return $constantReflection;
				}
			}

			return null;
		}

		throw new \PHPStan\ShouldNotHappenException();
	}

	public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
	{
		return []; // todo
	}

}
