<?php
/**
 * File containing the ezcArchiveCentralDirectoryHeader class.
 *
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 * 
 *   http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 * @package Archive
 * @version //autogentag//
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @access private
 */

/**
 * The ezcArchiveCentralDirectoryEndHeader class represents the Zip central directory end header.
 *
 * ezcArchiveCentralDirectoryEndHeader can read the header from an ezcArchiveCharacterFile.
 *
 * The values from the headers are directly accessible via the class properties, and allows
 * reading and writing to specific header values.
 *
 * The entire header can be appended to an ezcArchiveCharacterFile again.
 *
 * The central directory end header is the last header of the ZIP archive. Some algorithms
 * search back to this header in order to find the other headers. The variable comment length
 * makes it tricky.
 *
 * The Central Directory End Header has the following structure:
 *
 * <pre>
 * + ---+---------+------------+------------------------------------+-------------------------------------------------------------------------------+
 * | ID | Offset  | Field size | Property                           |  Description                                                                  |
 * +----+---------+------------+------------------------------------+-------------------------------------------------------------------------------+
 * |    |  0      | 4          | -                                  | Central directory header signature                                            |
 * |    |  4      | 2          | diskNumber                         | nr of this disk                                                               |
 * |    |  6      | 2          | centralDirectoryDisk               | number of the disk with the start of the central directory                    |
 * |    |  8      | 2          | totalCentralDirectoryEntriesOnDisk | total number of entries in the central directory on this disk                 |
 * |    |  10     | 2          | totalCentralDirectoryEntries       | total number of entries in the central directory                              |
 * |    |  12     | 4          | centralDirectorySize               | size of the central directory                                                 |
 * |    |  16     | 4          | centralDirectoryStart              | offset of start of central directory with respect to the starting disk number |
 * | X: |  20     | 2          | commentLength                      | .ZIP file comment length                                                      |
 * |    |  22     | X          | comment                            | .ZIP file comment                                                             |
 * +----+---------+------------+------------------------------------+-------------------------------------------------------------------------------+
 * </pre>
 *
 *
 * The columns of the table are:
 * - ID gives a label to a specific field or row in the table.
 * - Offset describes the start position of a header field.
 * - Field size describes the size of the field in bytes.
 * - Property is the name of the property that will be set by the header field.
 * - Description explains what this field describes.
 *
 *
 * @package Archive
 * @version //autogentag//
 * @access private
 */
class ezcArchiveCentralDirectoryEndHeader
{
    /**
     * Defines the signature of this header.
     */
    final public const magic = 0x06054b50;

    /**
     * Holds the properties of this class.
     *
     * @var array(string=>mixed)
     */
    private array $properties = [];

    /**
     * Creates and initializes a new header.
     *
     * If the ezcArchiveCharacterFile $file is null then the header will be empty.
     * When an ezcArchiveCharacterFile is given, the file position should be directly after the
     * signature of the header. This header will be read from the file and initialized in this class.
     *
     */
    public function __construct( ezcArchiveCharacterFile $file = null )
    {
        if ( !is_null( $file ) )
        {
            $this->properties = unpack (
                "vdiskNumber/".
                "vcentralDirectoryDisk/".
                "vtotalCentralDirectoryEntriesOnDisk/".
                "vtotalCentralDirectoryEntries/".
                "VcentralDirectorySize/".
                "VcentralDirectoryStart/".
                "vcommentLength",
                $file->read( 18 ) );

                $this->properties["comment"] = $file->read( $this->properties["commentLength"] );
        }
        else
        {
            $this->properties["diskNumber"] = 0;
            $this->properties["centralDirectoryDisk"] = 0;
            $this->properties["totalCentralDirectoryEntries"] = 0;
            $this->properties["totalCentralDirectoryEntriesOnDisk"] = 0;
            $this->properties["totalCentralDirectorySize"] = 0;
            $this->properties["totalCentralDirectoryStart"] = 0;

            $this->setComment( "" );
        }
    }

    /**
     * Sets the property $name to $value.
     *
     * @throws ezcBasePropertyNotFoundException if the property does not exist.
     * @throws ezcBasePropertyReadOnlyException if the property is read-only
     * @param string $name
     * @return void
     * @ignore
     */
    public function __set( $name, mixed $value )
    {
        switch ( $name )
        {
            case "diskNumber":
            case "centralDirectoryDisk":
            case "totalCentralDirectoryEntriesOnDisk":
                throw new ezcBasePropertyReadOnlyException( $name );

            case "totalCentralDirectoryEntries":
                $this->setTotalDirectoryEntries( $value );
                break;

            case "centralDirectorySize":
            case "centralDirectoryStart":
                $this->properties[$name] = $value;
                break;

            case "commentLength":
                throw new ezcBasePropertyReadOnlyException( $name );

            case "comment":
                $this->setComment( $value );
                break;

            default:
                throw new ezcBasePropertyNotFoundException( $name );
        }
    }

    /**
     * Returns the value of the property $name.
     *
     * @throws ezcBasePropertyNotFoundException if the property does not exist.
     * @param string $name
     * @return mixed
     * @ignore
     */
    public function __get( $name )
    {
        return match ($name) {
            "diskNumber", "centralDirectoryDisk", "totalCentralDirectoryEntriesOnDisk", "totalCentralDirectoryEntries", "centralDirectorySize", "centralDirectoryStart", "commentLength", "comment" => $this->properties[$name],
            default => throw new ezcBasePropertyNotFoundException( $name ),
        };
    }

    /**
     * Sets the comment and comment length in the header from the string $comment.
     *
     * @param string $comment
     * @return void
     */
    public function setComment( $comment )
    {
        $this->properties["comment"] = $comment;
        $this->properties["commentLength"] = strlen( $comment );
    }

    /**
     * Sets the total directory entries to the int $numberOfEntries.
     *
     * The properties: diskNumber and centralDirectory will be set to 0.
     * The properties: totalCentralDirectoryEntriesOnDisk and totalCentralDirectoryEntries are set to the $numberOfEntries.
     *
     * @param int $numberOfEntries
     * @return void
     */
    public function setTotalDirectoryEntries( $numberOfEntries )
    {
        $this->properties["diskNumber"] = 0;
        $this->properties["centralDirectoryDisk"] = 0;
        $this->properties["totalCentralDirectoryEntriesOnDisk"] = $numberOfEntries;
        $this->properties["totalCentralDirectoryEntries"] = $numberOfEntries;
    }

    /**
     * Serializes this header and appends it to the given ezcArchiveCharacterFile $archiveFile.
     *
     * @param ezcArchiveCharacterFile $archiveFile
     * @return void
     */
    public function writeEncodedHeader( $archiveFile )
    {
        $enc = pack ( "VvvvvVVv",
            self::magic,
            $this->properties["diskNumber"],
            $this->properties["centralDirectoryDisk"],
            $this->properties["totalCentralDirectoryEntriesOnDisk"],
            $this->properties["totalCentralDirectoryEntries"],
            $this->properties["centralDirectorySize"],
            $this->properties["centralDirectoryStart"],
            $this->properties["commentLength"] );

        $archiveFile->write( $enc . $this->properties["comment"] );
    }

    /**
     * Returns true if the given string $string matches with the current signature.
     *
     * @param string $string
     *
     * @return bool
     */
    public static function isSignature( $string )
    {
        return $string == pack( "V", self::magic );
    }
}
?>
