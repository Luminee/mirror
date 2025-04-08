<?php

namespace Luminee\Mirror\Http\Controllers;

use Exception;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class MirrorController extends Controller
{
    public function index()
    {
        if (request()->wantsJson()) {
            return $this->prepareCommands();
        }
        return view('mirror::index');
    }

    protected function prepareCommands(): array
    {
        $commands = [];
        $defined = Artisan::all();

        foreach ($defined as $name => $command) {
            $group = explode(':', $name)[0];
            if ($group == $name) {
                $group = '__';
            }

            $commands[$group][$name] = $this->commandToArray($command);
        }

        ksort($commands);

        foreach ($commands as $gKey => $group) {
            ksort($commands[$gKey]);
        }

        return $commands;
    }

    protected function commandToArray($command): array
    {
        return [
            'name' => $command->getName(),
            'description' => $command->getDescription(),
            'synopsis' => $command->getSynopsis(),
            'arguments' => $this->argumentsToArray($command),
            'options' => $this->optionsToArray($command),
        ];

    }

    protected function optionsToArray(Command $command)
    {
        $options = array_map(function (InputOption $option) {
            return [
                'title' => str_replace('_', ' ', snake_case($option->getName())),
                'name' => $option->getName(),
                'description' => $option->getDescription(),
                'shortcut' => $option->getShortcut(),
                'required' => $option->isValueRequired(),
                'array' => $option->isArray(),
                'accept_value' => $option->acceptValue(),
                'default' => empty($default = $option->getDefault()) ? null : $default,
            ];
        }, $command->getDefinition()->getOptions());

        return empty($options) ? null : $options;
    }

    protected function argumentsToArray(Command $command)
    {
        $arguments = array_map(function (InputArgument $argument) {
            return [
                'title' => str_replace('_', ' ', snake_case($argument->getName())),
                'name' => $argument->getName(),
                'description' => $argument->getDescription(),
                'default' => empty($default = $argument->getDefault()) ? null : $default,
                'required' => $argument->isRequired(),
                'array' => $argument->isArray(),
            ];
        }, $command->getDefinition()->getArguments());

        return empty($arguments) ? null : $arguments;
    }

    public function run($command)
    {
        $command = $this->findCommandOrFail($command);

        $rules = $this->buildRules($command);
        $data = request()->validate($rules);

        $params = $this->formatParams($command, $data);

        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        try {
            $status = Artisan::call($command->getName(), $params, $output);
            $output = $output->fetch();
        } catch (Exception $exception) {
            $status = $exception->getCode() ?? 500;
            $output = $exception->getMessage();
        }

        $res = [
            'status' => $status,
            'output' => $output,
            'command' => $command->getName()
        ];

        if (request()->wantsJson()) {
            return $res;
        }

        return back()
            ->with($res);
    }

    protected function findCommandOrFail(string $name): Command
    {
        $commands = Artisan::all();

        if (!in_array($name, array_keys($commands))) {
            abort(404);
        }

        return $commands[$name];
    }

    protected function buildRules(Command $command): array
    {
        $rules = [];

        foreach ($command->getDefinition()->getArguments() as $argument) {
            $rules[$argument->getName()] = [
                $argument->isRequired() ? 'required' : 'nullable',
            ];
        }

        foreach ($command->getDefinition()->getOptions() as $option) {
            $rules[$option->getName()] = [
                $option->isValueRequired() ? 'required' : 'nullable',
                $option->acceptValue() ? ($option->isArray() ? 'array' : 'string') : 'bool',
            ];
        }

        return $rules;
    }

    protected function formatParams(Command $command, $data): array
    {
        $data = array_filter($data);
        $options = array_keys($command->getDefinition()->getOptions());

        $params = [];

        foreach ($data as $key => $value) {

            if (in_array($key, $options)) {
                $key = "--$key";
            }

            $params[$key] = $value;
        }

        return $params;
    }

}
