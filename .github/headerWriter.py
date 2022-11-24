#!/usr/bin/env python3
import os
import os.path as path
import fileinput
import argparse
import sys


parser =argparse.ArgumentParser(description='Write or change license headers to vue, js or scss files')
parser.add_argument('-p', '--path', metavar='', required=True, help='path to root derictory of the project')
parser.add_argument('-a', '--automatic', action="store_true", default=False, required=False, help='trigger writing header automatically ')
parser.add_argument('-i', '--ignore', nargs='+', metavar='', required=False, help='path to derictories that should be ignored')
parser.add_argument('-u', '--update', action="store_true", default=False, required=False, help='update existing header (existing header template must be placed in header_files/last_used_header )')
parser.add_argument('-v', '--verbose', action="store_true", default=False, required=False, help='show more output')
args = parser.parse_args()


src_extensions = ['.vue', '.js', '.scss']
exclude_path = []

header = {
       '.js': '''/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

''',
    '.vue': '''<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

''',
    '.scss': '''//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//  (c) 2010-present DEMOS E-Partizipation GmbH.
//
//  This file is part of the package demosplan,
//  for more information see the license file.
//
//  All rights reserved
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

'''
}
last_used_header = {
       '.js': '''/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

''',
    '.vue': '''<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

''',
    '.scss': '''//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//  (c) 2010-present DEMOS E-Partizipation GmbH.
//
//  This file is part of the package demosplan,
//  for more information see the license file.
//
//  All rights reserved
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

'''
}
work = False

def match(first, second):

    length_first = len(first)
    length_second = len(second)

    if length_first == 0 and length_second == 0:
        return True

    if length_first > 1 and first[0] == '*':
        i = 0
        while i+1 < length_first and first[i+1] == '*':
            i = i+1
        first = first[i:]

    if length_first > 1 and first[0] == '*' and length_second == 0:
        return False

    if (length_first > 1 and first[0] == '?') or (length_first != 0 and length_second != 0 and first[0] == second[0]):
        return match(first[1:], second[1:])

    if length_first != 0 and first[0] == '*':
        return match(first[1:], second) or match(first, second[1:])

    return False


def get_src_files(dirname):
    src_files = dict()
    for cur, _dirs, files in os.walk(dirname):
        for file in files:
            result = next((ext for ext in src_extensions if file.endswith(ext)), None)
            if result :
                if is_header_missing(result,(path.join(cur,file))):
                    yield result, path.join(cur,file)


def is_header_missing(ext,path):
    with open(path) as reader:
        lines = reader.read().lstrip().splitlines()
        header_check = header.get(ext).splitlines()[1]
        if len(lines) > 1:
            if (match(lines[1],header_check)):
                if (update):
                    return is_header_changed(ext, path)
                else:
                    return False
            return True


def is_header_changed(ext, path):
    with open(path) as reader:
        old_header = reader.read()
        if(last_used_header.get(ext)):
            header_check = last_used_header.get(ext)
            if (old_header[0:len(header_check)] == header_check):

                return delete_header(old_header,header_check,path)
            else:
                return False
        else:
            return False


def delete_header(old_header,teststr,path):
    if args.verbose:
        sys.stderr.write("deleting header of:"+path+'\n')

    new_text = old_header.replace(teststr, '')
    open(path,'w').write(new_text)
    return True

def add_headers(files, header):
    for key in files:
        if(files.get(key)):
              for line in fileinput.input(files.get(key), inplace=True):
                if fileinput.isfirstline():
                    [ print(h) for h in header.get(key).splitlines() ]
                print(line, end="")
    print("Done here")


def filter_files():
    for ext, file in get_src_files(root_path):
        tested_file = []
        for path in exclude_path:
            tested_file.append(not(match(path ,file)))
        if all(tested_file):
            global work
            work = True
            yield ext,file



if __name__ == "__main__":

    update = args.update
    root_path = args.path
    files ={}

    if args.ignore:
        for ignore in args.ignore:
            exclude_path.append(root_path+'/'+ignore)
        if args.verbose:
            print("Ignored path:\n", exclude_path)


    for ext, file in filter_files():
        if ext in files:
            files[ext].append(file)
        else:
            files[ext] = [file]

    if args.verbose:
        sys.stderr.write("\nFiles with missing headers:")
        for ext in files:
            sys.stderr.write('\n'+ext+':\n')
            for file in files.get(ext):
                sys.stderr.write(file+'\n')


    print("\nHeader: ")

    for key, value in header.items() :
        print ('\n', key, ':\n','\n', value)

    if work:
        if not args.automatic:
            confirm = input("proceed ? [y/N] ")
            if (confirm != "y"):
                exit(0)
        add_headers(files, header)
    else:
        print('\n\nall files are up to date\n\n')
        exit(0)
    exit(0)